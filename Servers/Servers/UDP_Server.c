#include <stdio.h>     //standard I/O library for printing logs and debugging
#include <winsock2.h>  //windows sockets API for networking
#include <windows.h>   //windows threading and synchronization functions
#include <time.h>      //for logging the events with their timestamps

#pragma comment(lib,"ws2_32.lib") //link Winsock2 library for networking

//define constants for ports , buffer sizes and broadcast IP address
#define IP_BROADCAST_PORT 9091         //port for broadcassting the server's IP address
#define MESSAGE_RECEIVE_PORT 9092      //port for receiving messages from clients
#define MESSAGE_BROADCAST_PORT 9093    //port for broadcasting messages to all clients
#define BROADCAST_IP "255.255.255.255" //broadcast IP to send data to all clients 
#define BUFFER_SIZE_IP 50              //buffer size for storing the serve's IP address
#define BUFFER_SIZE_MSG 2048           //buffer size for receiving and broadcasting messages
#define LOG_FILE "UDP_server_log.txt"  // log file to store events with timestamps

//global variables for server control and sockets
volatile int serverRunning = 1; //flag to determine whether the server is running or not
WSADATA wsa;                    //holds windows socket initialization data
SOCKET ipBroadcastSocket, messageReceiveSocket, messageBroadcastSocket; //three sockets for IP broadcasting , message receiving , and message broadcasting 
struct sockaddr_in ipBroadcastAddr, msgReceiveAddr, msgBroadcastAddr;   //structures to store address configurations
char serverIP[BUFFER_SIZE_IP];             //buffer to store server's IP address
char lastReceivedMessage[BUFFER_SIZE_MSG]; //buffer to store the last received message

//function to log events
void logMessage(const char *message) {
    FILE *logFile = fopen(LOG_FILE, "a"); // Open log file in append mode
    if (logFile) {
        char timestamp[30]; // Buffer to hold the timestamp
        time_t now = time(NULL);
        struct tm *tm_info = localtime(&now);
        strftime(timestamp, sizeof(timestamp), "[%Y-%m-%d %H:%M:%S]", tm_info); // Format timestamp

        // Write formatted log entry
        fprintf(logFile, "%s : %s\n", timestamp, message);
        fclose(logFile);
    }
}

//function to retrieve the server's dynamic IP address
void getServerIP(char *ipBuffer) {
    char hostname[256]; //buffer to store the hostname of the local machine

    //retry getting the hostname if an error occurs
    while(gethostname(hostname,sizeof(hostname)) == SOCKET_ERROR) {
        printf("Error getting hostname : %d\n", WSAGetLastError());
        Sleep(1000); //wait 2 seconds before retrying
    }

    struct hostent *host = gethostbyname(hostname); //get host details based on the hostname
    if(host == NULL) {
        printf("Error getting host info : %d\n", WSAGetLastError());
        return;
    }

    //store the IP address found to ipBuffer
    strcpy(ipBuffer, inet_ntoa(*(struct in_addr *)host->h_addr_list[0]));
}

//thread to continuously broadcast the server's IP address (every 3 seconds)
DWORD WINAPI broadcastIPThread(LPVOID lpParam) {
    printf("Server IP address is being broadcasted every 3 seconds.\n");
    logMessage("Server IP address is being broadcasted every 3 seconds.");
    while(serverRunning){
        if(sendto(ipBroadcastSocket,serverIP,strlen(serverIP),0,(struct sockaddr *)&ipBroadcastAddr,sizeof(ipBroadcastAddr)) == SOCKET_ERROR) {
            printf("Failed to send IP broadcast.\n");
            logMessage("Failed to send IP broadcast.");
        }
        Sleep(3000); //broadcast every 3 seconds
    }
    return 0;
}

//thread to continuously listen for incoming text messages from clients
DWORD WINAPI receiveMessageThread(LPVOID lpParam) {
    char buffer[BUFFER_SIZE_MSG];     //buffer for incoming messages
    struct sockaddr_in clientAddr;    //stores clients address info
    int addrLen = sizeof(clientAddr); //size of the client address structure

    while(serverRunning){
        int receivedBytes = recvfrom(messageReceiveSocket, buffer, BUFFER_SIZE_MSG, 0, (struct sockaddr *)&clientAddr, &addrLen);
        if(receivedBytes > 0) {
            buffer[receivedBytes] = '\0'; //null-terminate the received string
            printf("Received message : %s\n", buffer);
            logMessage(buffer);

            //store received message for broadcasting
            strncpy(lastReceivedMessage, buffer, BUFFER_SIZE_MSG);
        }
    }
    return 0;
}

//thread to broadcast the last received message to all clients
DWORD WINAPI broadcastMessageThread(LPVOID lpParam) {
    while (serverRunning) {  // Keep running while the server is active

        // Check if there is a message to broadcast
        if (strlen(lastReceivedMessage) > 0) {  

            // Send the message via the broadcast socket
            if (sendto(messageBroadcastSocket, lastReceivedMessage, strlen(lastReceivedMessage), 0, (struct sockaddr*)&msgBroadcastAddr, sizeof(msgBroadcastAddr)) == SOCKET_ERROR) {
                // If sending fails, log an error message
                printf("Failed to broadcast message.\n");
                logMessage("Failed to broadcast message.");
            } 
            else {
                // If sending succeeds, log success and show the message
                printf("Message broadcasted successfully: %s\n", lastReceivedMessage);
                logMessage("Message was broadcasted successfully.");
                
                // Reset the message buffer after broadcasting to allow re-broadcasting of identical messages
                lastReceivedMessage[0] = '\0';
            }
        }
        Sleep(100);  // Small delay to prevent excessive CPU usage in the loop
    }
    return 0;
}

int main() {
    printf("UDP Server starting...\n");
    logMessage("UDP Server starting...");
    
    if(WSAStartup(MAKEWORD(2,2),&wsa) != 0) {
        printf("Winsock initialization failed..\n");
        logMessage("Winsock initialization failed..");
        return 1;
    }

    printf("Winsock initialized successfully..\n");
    logMessage("Winsock initialized successfully..");
    //create sockets for broadcasting IP, receiving messages, andbroadcasting messages
    ipBroadcastSocket = socket(AF_INET, SOCK_DGRAM, 0);
    messageReceiveSocket = socket(AF_INET, SOCK_DGRAM, 0);
    messageBroadcastSocket = socket(AF_INET, SOCK_DGRAM, 0);

    if(ipBroadcastSocket == SOCKET_ERROR || messageReceiveSocket == SOCKET_ERROR || messageBroadcastSocket == SOCKET_ERROR) {
        printf("Failed to create the sockets.\n");
        logMessage("Failed to create the scokets.");
        WSACleanup();
        return 1;
    }

    //enable broadcsting for UDP sockets
    int enableBroadcasting = 1;
    setsockopt(ipBroadcastSocket, SOL_SOCKET, SO_BROADCAST, (char *)&enableBroadcasting, sizeof(enableBroadcasting));
    setsockopt(messageBroadcastSocket, SOL_SOCKET, SO_BROADCAST, (char *)&enableBroadcasting, sizeof(enableBroadcasting));

    //configure the IP broadcast address
    ipBroadcastAddr.sin_family = AF_INET;
    ipBroadcastAddr.sin_addr.s_addr = inet_addr(BROADCAST_IP);
    ipBroadcastAddr.sin_port = htons(IP_BROADCAST_PORT);

    //configure the message receiving socket
    msgReceiveAddr.sin_family = AF_INET;
    msgReceiveAddr.sin_addr.s_addr = INADDR_ANY;
    msgReceiveAddr.sin_port = htons(MESSAGE_RECEIVE_PORT);

    //configure the message broadcasting socket
    msgBroadcastAddr.sin_family = AF_INET;
    msgBroadcastAddr.sin_addr.s_addr = inet_addr(BROADCAST_IP);
    msgBroadcastAddr.sin_port = htons(MESSAGE_BROADCAST_PORT);

    //bind the receiving sockets so it can listen for incoming messages
    if(bind(messageReceiveSocket,(struct sockaddr*)&msgReceiveAddr,sizeof(msgReceiveAddr)) == SOCKET_ERROR) {
        printf("Binding failed for message receive socket.\n");
        logMessage("Binding failed for message receive socket.");
        closesocket(messageReceiveSocket);
        WSACleanup();
        return 1;
    }

    getServerIP(serverIP);
    printf("Server IP retreived : %s\n", serverIP);
    logMessage("Server IP retreived");

    //start the background threads
    HANDLE hIPThread = CreateThread(NULL, 0, broadcastIPThread, NULL, 0, NULL);
    HANDLE hRecvThread = CreateThread(NULL, 0, receiveMessageThread, NULL, 0, NULL);
    HANDLE hBroadcastThread = CreateThread(NULL, 0, broadcastMessageThread, NULL, 0, NULL);

    printf("UDP Server is running successfully press ESC button to stop it.\n");
    logMessage("UDP Server started successfully.");

    //while loop to keeep the server running until ESC is pressed
    while(serverRunning) {
        if(GetAsyncKeyState(VK_ESCAPE)) { //check if ESC key is pressed
            printf("ESC detected. shutting down server...\n ");
            logMessage("Server shutting down due to ESC key press.");
            serverRunning = 0; //stop the server
            Sleep(100);        //small delay to prevent excessive looping
        }
    }

    //cleanup before exiting
    logMessage("Cleaning up resources...");
    closesocket(ipBroadcastSocket);
    closesocket(messageReceiveSocket);
    closesocket(messageBroadcastSocket);
    WSACleanup();

    printf("Server stopped successfully.\n");
    logMessage("Server stopped successfully.");

    return 0;
}