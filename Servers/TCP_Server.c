#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <ctype.h> //for isspcae() 
#include <winsock2.h>
#include <windows.h> //for CreateThread


#pragma comment(lib,"ws2_32.lib") //linking with winsock library

#define text_buffer_size 1024     //1KB for text messages

//global variables decleration for broadcasting the messages
SOCKET clients[FD_SETSIZE];
int clients_count = 0;
CRITICAL_SECTION client_list_lock;

//a function to check if the string sent by client is empty or having whiteSpaces
int is_empty_or_whitespace(const char *str) {
while(*str) {
    if(!isspace(*str)) {
        return 0;//string is not empty there is a character in it
        }
        str++;//move to the next character
    }
    return 1;//string is empty or contains only of whiteSpaces
}

//adding the clients to the global list
void add_client(SOCKET client_fd) {
    EnterCriticalSection(&client_list_lock);
    clients[clients_count++] = client_fd;
    LeaveCriticalSection(&client_list_lock);
}

//removing the clients from the global list
void remove_client(SOCKET client_fd) {
    EnterCriticalSection(&client_list_lock);
    for (int i = 0; i < clients_count;i++) {
        if(clients[i] == client_fd) {
            clients[i] = clients[--clients_count];
            printf("Client removed successfully.\nRemaining clients : %d\n", clients_count);
            break;//exit loop after removal
        }
    }
    LeaveCriticalSection(&client_list_lock);
}

//broadcasting the massage to all clients except the sender 
void broadcast_message(SOCKET sender_fd, const char *message) {
    EnterCriticalSection(&client_list_lock);
    for (int i = 0; i < clients_count; i++) {
        if (clients[i] != sender_fd) { // Skip the sender
            if (send(clients[i], message, strlen(message), 0) == SOCKET_ERROR) {
                printf("Failed to send message to client %d.\n", i);
                closesocket(clients[i]);
                remove_client(clients[i]); // Remove problematic client
                i--; // Adjust index for shifted array
            }
        }
    }
    LeaveCriticalSection(&client_list_lock);
}


//a function to handle communication with a single client
DWORD WINAPI client_handler(LPVOID client_socket) {
    SOCKET client_fd = *(SOCKET *)client_socket;
    char text_buffer[text_buffer_size] = {0};
//communication loop 
    while (1) {
        memset(text_buffer, 0, text_buffer_size); // Clear the buffer
        int bytes_received = recv(client_fd, text_buffer, text_buffer_size - 1, 0); // Receive data
        if (bytes_received > 0) {
            text_buffer[bytes_received] = '\0'; // Null-terminate the received data
            if (!is_empty_or_whitespace(text_buffer)) { // Validate message
                printf("Client: %s\n", text_buffer);
                broadcast_message(client_fd, text_buffer); // Broadcast the message
            } else {
                const char *warning_message = "Empty messages are not allowed!\n";
                send(client_fd, warning_message, strlen(warning_message), 0);
                printf("Empty message recieved from %d", clients[client_fd]);
            }
        } else if (bytes_received == 0) { // Client closed connection
            printf("Client disconnected...!\n");
            break; // Exit loop
        } else { // Error occurred
            printf("Error receiving data from client occurred.\n");
            break;
        }
    }

    remove_client(client_fd);//remove client from broadcasting list
    closesocket(client_fd);//close client socket
    free(client_socket);//free memory
    return 0;
    }

int main() {
    WSADATA wsa;
    SOCKET server_fd,client_fd;
    struct sockaddr_in server_address,client_address;
    int client_address_length = sizeof(client_address), port_number = 8080;

    InitializeCriticalSection(&client_list_lock);//initialize client lock
    //initialize winsock 
    printf("Initializing Winsock...\n");
    if(WSAStartup(MAKEWORD(2,2), &wsa) != 0) {
        printf("Winsock Initialization Failed...!\nError Code : %d\n", WSAGetLastError());
        DeleteCriticalSection(&client_list_lock);//cleanup client lock
        return 1;
    }
    printf("Winsock Initialized successfully!\n");

    //create the server socket
    server_fd = socket(AF_INET,SOCK_STREAM,0); 
    if(server_fd == INVALID_SOCKET) {
        printf("Socket Creation Failed...!\nError Code : %d\n", WSAGetLastError());
        WSACleanup();
        DeleteCriticalSection(&client_list_lock);//cleanup client lock
        return 1;
    }

    //bind the socket to the address and port number
    server_address.sin_family = AF_INET;
    server_address.sin_addr.s_addr = INADDR_ANY;
    server_address.sin_port = htons(port_number);

    if(bind(server_fd,(struct sockaddr *)&server_address,sizeof(server_address)) == SOCKET_ERROR) {
        printf("Binding failed...!\nError Code : %d\n", WSAGetLastError());
        closesocket(server_fd);
        WSACleanup();
        DeleteCriticalSection(&client_list_lock);//cleanup client lock
        return 1;
    }

// Listen for incoming connections
    if (listen(server_fd, 10) == SOCKET_ERROR) {
        printf("Listening failed...!\nError Code: %d\n", WSAGetLastError());
        closesocket(server_fd);
        WSACleanup();
        DeleteCriticalSection(&client_list_lock);//cleanup client lock
        return 1;
    }
    printf("Server is listening now on port %d\n", port_number);

    while(1) {
        //accept incoming connection from client
        client_fd = accept(server_fd, (struct sockaddr *)&client_address, &client_address_length);
        if(client_fd == INVALID_SOCKET) {
            printf("Failed to accept connection...!\n");
            continue;//skip this client
        }
        printf("Client connected successfully.\n");

        // memory allocation for client_fd
        SOCKET *client_socket = malloc(sizeof(SOCKET));
        *client_socket = client_fd;

        //notify all other clients about the new connection
        char connect_message[text_buffer_size];
        snprintf(connect_message, sizeof(connect_message),
        "A new client has joined the chat.Say hello...!");
        broadcast_message(client_fd, connect_message);

        add_client(client_fd); //it has been put after the notification block in order to prevent the same person from being notified

        //create a new thread to handle the client
        HANDLE thread = CreateThread(NULL, 0, client_handler, client_socket, 0, NULL);
        if(thread == NULL) {
        printf("Failed to create thread for client...!\n");
        closesocket(client_fd);
        free(client_socket);//free the allocated memory
        remove_client(client_fd);
        }
        else {
            CloseHandle(thread);//close the thread handle if successfully created
        }
    }
    // Clean up server resources during shutdown
    closesocket(server_fd); // Close server socket
    EnterCriticalSection(&client_list_lock); // Ensure no race conditions
    for (int i = 0; i < clients_count; i++) {
    closesocket(clients[i]); // Close all client sockets
    }
    LeaveCriticalSection(&client_list_lock);
    DeleteCriticalSection(&client_list_lock); // Cleanup critical section
    WSACleanup(); // Cleanup Winsock
    return 0;
}