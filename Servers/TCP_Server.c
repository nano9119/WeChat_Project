#include <stdio.h>        //standard I/O functions
#include <winsock2.h> //windows sockets API for networking
#include <windows.h>  //windows threading & synchronization functions
#include <time.h>         //time functions for logging
#include <direct.h>       //directory management


#pragma comment(lib,"ws2_32.lib") //link Winsock2 library

//configuration of constants
#define SERVER_PORT 9090            //port for client file transfers
#define MAX_CLIENTS 10              //maximum number of connected clients 
#define BUFFER_SIZE 4096            //buffer size for file transfer
#define SAVE_DIR "received_files/"  //directory to store received files
#define LOG_FILE "TCP_server_log.txt"   //log file to record transfers

//global variables
CRITICAL_SECTION fileLock;      //synchronization lock for shared file access
volatile int newFileReady = 0;  //flag indicating a new file is ready to broadcast
volatile int serverRunning = 1; //flag to allow graceful shutdown
char lastReceivedFile[256];     //stores the most recently received file name

//maintaining a list of connected clients for broadcasting
SOCKET clientSockets[MAX_CLIENTS]; //array to store clinet sockets
int clientCount = 0;               //tracks number of connected clients 

//structure to store connected client information
typedef struct {
    SOCKET clientSocket; //client socket descriptor
    int id;              //unique identifier for the client
} ClientInfo;

//threads and functions initial declaration
DWORD WINAPI receiveFileThread(LPVOID lpParam);   //handles file reception

DWORD WINAPI broadcastFileThread(LPVOID lpParam); //handles file broadcasting
void addClient(SOCKET clientSocket);              //adds a client to list
void removeClient(SOCKET clientSocket);          //removes a client from list
void logMessage(const char *message);             //logs events to file

int main(){
    //winsock initializtion
    WSADATA wsa;
    SOCKET serverSocket, clientSocket;
    struct sockaddr_in serverAddr, clientAddr;
    int clientAddrLen = sizeof(clientAddr);

    if(WSAStartup(MAKEWORD(2,2),&wsa) != 0) {
        printf("Winsock initialization failed : %d\n", WSAGetLastError());
        return 1;
    }

    //initialize critical section for thread safety
    InitializeCriticalSection(&fileLock);

    //create directory for received files
    _mkdir(SAVE_DIR);

    //creating TCP server socket
    serverSocket = socket(AF_INET, SOCK_STREAM, 0);
    if(serverSocket == INVALID_SOCKET) {
        printf("Failed to create TCP socket : %d\n", WSAGetLastError());
        WSACleanup();
        return 1;
    }

    //binding server socket
    serverAddr.sin_family = AF_INET;
    serverAddr.sin_addr.s_addr = INADDR_ANY;
    serverAddr.sin_port = htons(SERVER_PORT);

    if(bind(serverSocket,(struct sockaddr*)&serverAddr,sizeof(serverAddr)) == SOCKET_ERROR) {
        printf("Binding failed : %d\n", WSAGetLastError());
        closesocket(serverSocket);
        WSACleanup();
        return 1;
    }

    //start listening for incoming connections
    listen(serverSocket, MAX_CLIENTS);
    printf("Server listening on port %d...\n", SERVER_PORT);
    logMessage("Server started and listening for connections.");

    //start broadcast thread
    HANDLE hBroadcastThread = CreateThread(NULL, 0, broadcastFileThread, NULL, 0, NULL);

    while(serverRunning) {
        //accept new clients
        clientSocket = accept(serverSocket, (struct sockaddr*)&clientAddr, &clientAddrLen);
        if(clientSocket == INVALID_SOCKET) {
            printf("Accept failed : %d\n", WSAGetLastError());
            continue;
        }
        printf("New client connected!\n");
        logMessage("New client connected.");

        //add client to list for broadcasting
        addClient(clientSocket);

        //create thread to handle file reception from this client
        ClientInfo* clientInfo = (ClientInfo*)malloc(sizeof(ClientInfo));
        clientInfo->clientSocket = clientSocket;
        clientInfo->id = rand();
        CreateThread(NULL, 0, receiveFileThread, clientInfo, 0, NULL);
        
    }
    closesocket(serverSocket);
    WSACleanup();
    DeleteCriticalSection(&fileLock);
    return 0;
}

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


//function to add client
void addClient(SOCKET clientSocket) {
    if(clientCount < MAX_CLIENTS) {
        clientSockets[clientCount++] = clientSocket;
    }
}

//function to remove client
void removeClient(SOCKET clientSocket) {
    for (int i = 0; i < clientCount;i++){
        if(clientSockets[i] == clientSocket) {
            for (int j = i; j < clientCount - 1;j++) {
                clientSockets[j] = clientSockets[j + 1];
            }
            clientCount--;
            break;
        }
    }
}

//thread to receive files
DWORD WINAPI receiveFileThread(LPVOID lpParam) {
    
    ClientInfo* clientInfo = (ClientInfo*)lpParam;
    SOCKET clientSocket = clientInfo->clientSocket;
    free(clientInfo); //free allocated memory after use
    clientInfo = NULL;

    char buffer[BUFFER_SIZE];
    FILE* file = NULL;
    int fileOpenFlag = 0;

    while(serverRunning) {
        int bytesReceived = recv(clientSocket, buffer, BUFFER_SIZE, 0);
        if (bytesReceived <= 0) {
            printf("Client disconnected.\n");
            logMessage("Client disconnected.");
            removeClient(clientSocket);
            closesocket(clientSocket);
            return 0;
        }

        //critical section protection
        EnterCriticalSection(&fileLock);

        // Check for metadata (Header or EOF packets)
        if (strncmp(buffer, "[METADATA] ", 11) == 0) {
            sscanf(buffer + 11, "%s", lastReceivedFile);

            char fullPath[512];
            _snprintf(fullPath, sizeof(fullPath), "%s%s", SAVE_DIR, lastReceivedFile);
            
            printf("Receiving file: %s\n", fullPath);
            logMessage("Receiving new file.");

            file = fopen(fullPath, "wb");
            if (!file) {
                printf("Error opening file.\n");
                LeaveCriticalSection(&fileLock);
                continue;
            }
            fileOpenFlag = 1;
        }
        else if (fileOpenFlag && strncmp(buffer, "[EOF]", 5) != 0) {
            fwrite(buffer, sizeof(char), bytesReceived, file);
        } else if (fileOpenFlag && strncmp(buffer, "[EOF]", 5) == 0) {
            printf("File received successfully!\n");
            logMessage("File received.");
            fclose(file);
            fileOpenFlag = 0;
            newFileReady = 1;
        }
        LeaveCriticalSection(&fileLock);
    }
}

//thread to broadcast files 
DWORD WINAPI broadcastFileThread(LPVOID lpParam) {
    while(serverRunning) {
        if(newFileReady) {
            EnterCriticalSection(&fileLock);
            char fullPath[512];
            _snprintf(fullPath, sizeof(fullPath), "%s%s", SAVE_DIR, lastReceivedFile);
            FILE* file = fopen(fullPath, "rb");
            if(!file) {
                LeaveCriticalSection(&fileLock);
                continue;
            }

            printf("Broadcasting file : %s\n", lastReceivedFile);
            logMessage("Broadcasting file.");

            char buffer[BUFFER_SIZE];

            // Send HEADER packet first
            sprintf(buffer, "[METADATA] %s", lastReceivedFile);
            for (int i = 0; i < clientCount; i++) {
                send(clientSockets[i], buffer, strlen(buffer), 0);
            }

             // Send raw binary file data
            size_t bytesRead;
            while ((bytesRead = fread(buffer, sizeof(char), BUFFER_SIZE, file)) > 0) {
                for (int i = 0; i < clientCount; i++) {
                    send(clientSockets[i], buffer, bytesRead, 0); // Binary data only
                }
            }

            // Send EOF packet
            strcpy(buffer, "[EOF]");
            for (int i = 0; i < clientCount; i++) {
                send(clientSockets[i], buffer, strlen(buffer), 0);
            }


            fclose(file);
            newFileReady = 0;

            LeaveCriticalSection(&fileLock);
        }
        Sleep(500);
    }
    return 0;
}