/**
 * -----------------------------------------------------------
 *  Crafted by nano9119 | April 2025
 *  A multi-threaded, UDP-powered beast built with passion. 
 *  This isn't just a college project—it's a legacy.
 * -----------------------------------------------------------
 */
#include <stdio.h>    //standard I/O library for printing logs
#include <stdlib.h>   //standard library for memory management
#include <string.h>   //string manipulation functions
#include <winsock2.h> //windows sockets API for networking
#include <time.h>     //time library to generate timestamps for file names
#include <windows.h>  //windows threading and synchronization
#include <direct.h>   //for directory creation on Windows

#pragma comment(lib,"ws2_32.lib") //link Winsock2 library to enable networking functions

//define networking ports for different functionalities
#define SERVER_PORT 9090               //port for receiving text files
#define IP_BROADCAST_PORT 9091         //port for broadcasting IP address
#define FILE_BROADCAST_PORT 9092       //port for broadcasting text files
#define BROADCAST_IP "255.255.255.255" //broadcast address

//buffer sizes for handling data
#define BUFFER_SIZE_IP 50              //IP buffer size
#define BUFFER_SIZE_FILE 2048          //file data buffer size
#define SAVE_DIR "received_files/"     //directory to store received files

//define global variables for network sockets and addresses
WSADATA wsa;                                                                   //structure to hold windows socket data
SOCKET ipBroadcastSocket, fileBroadcastSocket, fileSocket;                     //sockets for networking
struct sockaddr_in ipBroadcastAddr, fileBroadcastAddr, serverAddr, clientAddr; //structures to store address info
char serverIP[BUFFER_SIZE_IP];                                                 //buffer to hold server's IP address 
int clientAddrLen = sizeof(clientAddr);                                        //size of client address structure

//synchronization mechanism using a critical section for thread safety
CRITICAL_SECTION fileLock;     //protects shared file access
volatile int newFileReady = 0; //flag indicating a new file is ready for broadcasting
char lastReceivedFile[100];    //stores the latest received file name

//a function to get the server's dynamic IP address(the IP address is stored in the buffer)
void getServerIP(char *ipBuffer) {
    WSAStartup(MAKEWORD(2, 2), &wsa);                                          //initialize Winsock API
    char hostname[256];
    gethostname(hostname, sizeof(hostname));                                   //get the hostname of the local machine
    struct hostent *host = gethostbyname(hostname);                            //get host details based on the hostname
    strcpy(ipBuffer, inet_ntoa(*(struct in_addr *)host->h_addr_list[0]));      //extract the IP address
    WSACleanup();                                                              //cleanup Winsock after retrieving IP address
}

//thread function to broadcast the server's IP address every 3 seconds
DWORD WINAPI broadcastIPThread(LPVOID lpParam) {
    printf("Broadcasting server IP : %s\n", serverIP);
    while(1) {
        //send IP address over UDP
        if(sendto(ipBroadcastSocket,serverIP,strlen(serverIP),0,(struct sockaddr *)&ipBroadcastAddr,sizeof(ipBroadcastAddr)) == SOCKET_ERROR) {
            printf("Error sending IP broadcast : %d\n", WSAGetLastError());
        }
        Sleep(3000); //broadcasting every 3 seconds
    }
    return 0;
}

//thread function to receive incoming text files
DWORD WINAPI receiveFileThread(LPVOID lpParam) {
    char fileBuffer[BUFFER_SIZE_FILE]; //buffer for  receiving file chunks
    int fileOpenFlag = 0;             //flag to track if the file is opened

    _mkdir(SAVE_DIR);                 //Create "received_files/" folder

    while(1) {
        //listen for incoming data packets from a client
        int bytesReceived = recvfrom(fileSocket, fileBuffer, BUFFER_SIZE_FILE, 0, (struct sockaddr *)&clientAddr, &clientAddrLen);
        if(bytesReceived == SOCKET_ERROR) {
            printf("Error receiving data : %d\n", WSAGetLastError());
            continue;
        }
        fileBuffer[bytesReceived] = '\0'; //null-terminate received data

        //end of file checker (EOF)
        if(strcmp(fileBuffer,"EOF") == 0) {
            newFileReady = 1; //trigger the broadcasting thread
            fileOpenFlag = 0; //reset the flag so a new file is created for the next transmission
            printf("End of file received, ready to broadcast...!\n");
            continue;
        }

        //if file is not opened yet , generate a unique filename based on the current timestamp 
        if(fileOpenFlag == 0) {
            time_t now = time(NULL);
            struct tm *tm_info = localtime(&now);
            strftime(lastReceivedFile, sizeof(lastReceivedFile), SAVE_DIR "received_%Y%m%d_%H%M%S.txt", tm_info);
            fileOpenFlag = 1; //mark the file as opened
        }
        // lock the critical section before writing to the file to prevent data corruption
        EnterCriticalSection(&fileLock);
        FILE *file = fopen(lastReceivedFile, "a"); // open file in append mode
        if (file){
            fwrite(fileBuffer, sizeof(char), bytesReceived, file);
            fclose(file);
            }
        else {
            printf("Error saving file : %s\n", lastReceivedFile);
        }
        LeaveCriticalSection(&fileLock);        //unlock file access
    }
    return 0;
}

//thread function to broadcast the latest received file
DWORD WINAPI broadcastFileThread(LPVOID lpParam) {
    while(1) {
        if(newFileReady) {                  //check if a new file is ready for broadcasting
            EnterCriticalSection(&fileLock);//lock access to prevent conflicts

            FILE *file = fopen(lastReceivedFile, "r");   //open the latest received file in read mode
            if(file) {
                char fileBuffer[BUFFER_SIZE_FILE];
                size_t bytesRead;

                printf("Broadcasting file : %s\n", lastReceivedFile);
                
                //read the file chunk by chunk until fully transmitted
                while((bytesRead = fread(fileBuffer,sizeof(char),BUFFER_SIZE_FILE - 1,file)) > 0){
                    fileBuffer[bytesRead] = '\0'; //ensure proper null termination

                    //broadcast the file contents over UDP
                    int sendResult = sendto(fileBroadcastSocket, fileBuffer, bytesRead, 0, (struct sockaddr *)&fileBroadcastAddr, sizeof(fileBroadcastAddr));

                    if(sendResult == SOCKET_ERROR) {
                        printf("Error broadcasting file : %s (Error code : %d)\n", lastReceivedFile, WSAGetLastError());
                        break;
                    }
                }
                fclose(file);     //close file after sending all chunks
                newFileReady = 0; //reset flag after broadcasting the file
            }
            else {
                printf("Error reading file for broadcasting : %s\n", lastReceivedFile);
            }
            LeaveCriticalSection(&fileLock); // unlock file access
        }
        Sleep(500);        //check every 0.5 seconds for new files
    }
    return 0;
}

int main(){
    WSAStartup(MAKEWORD(2, 2), &wsa);     //initialize Winsock API
    
    InitializeCriticalSection(&fileLock); //initialize synchronization mechanism

    //create sockets for IP broadcasting,file broadcasting,and file reception
    ipBroadcastSocket = socket(AF_INET, SOCK_DGRAM, 0);
    fileBroadcastSocket = socket(AF_INET, SOCK_DGRAM, 0);
    fileSocket = socket(AF_INET, SOCK_DGRAM, 0);

    //enable broadcasting for IP and file sockets
    int enableBroadcasting = 1;
    setsockopt(ipBroadcastSocket, SOL_SOCKET, SO_BROADCAST, (char *)&enableBroadcasting, sizeof(enableBroadcasting));
    setsockopt(fileBroadcastSocket, SOL_SOCKET, SO_BROADCAST, (char *)&enableBroadcasting, sizeof(enableBroadcasting));

    //configure the IP and file broadcast addresses
    ipBroadcastAddr.sin_family = AF_INET;
    ipBroadcastAddr.sin_addr.s_addr = inet_addr(BROADCAST_IP);
    ipBroadcastAddr.sin_port = htons(IP_BROADCAST_PORT);

    fileBroadcastAddr.sin_family = AF_INET;
    fileBroadcastAddr.sin_addr.s_addr = inet_addr(BROADCAST_IP);
    fileBroadcastAddr.sin_port = htons(FILE_BROADCAST_PORT);

    serverAddr.sin_family = AF_INET;
    serverAddr.sin_addr.s_addr = INADDR_ANY;
    serverAddr.sin_port = htons(SERVER_PORT);
    if(bind(fileSocket, (struct sockaddr *)&serverAddr, sizeof(serverAddr)) == SOCKET_ERROR){
        printf("Error binding file socket : %d\n", WSAGetLastError());
        return 1;
    }

    getServerIP(serverIP);
    printf("server running at : %s\n", serverIP);

    //create three independent threads for broadcasting IP ,receiving files and broadcasting files
    HANDLE hThreads[] = {
        CreateThread(NULL, 0, broadcastIPThread, NULL, 0, NULL),
        CreateThread(NULL, 0, receiveFileThread, NULL, 0, NULL),
        CreateThread(NULL, 0, broadcastFileThread, NULL, 0, NULL)
        };

    WaitForMultipleObjects(3, hThreads, TRUE, INFINITE);

    closesocket(ipBroadcastSocket);
    closesocket(fileBroadcastSocket);
    closesocket(fileSocket);
    WSACleanup();
    DeleteCriticalSection(&fileLock);
    return 0;
} 