# PHP Unix Socket Container Architecture

A lightweight microservices architecture implementation using PHP and Unix sockets for inter-service communication.

## Overview

This project demonstrates a containerized microservice architecture where each service runs in an isolated environment and communicates with other services via Unix sockets. The architecture provides a simple yet effective way to build modular, scalable applications using PHP without the overhead of HTTP-based communication.

## Key Features

- **Containerized Services**: Each service runs in its own isolated process
- **Unix Socket Communication**: Fast, efficient inter-service communication
- **Process-based Orchestration**: Simple orchestration of multiple services
- **JSON-based Messaging**: Standardized message format for service communication
- **Automatic Service Discovery**: Services can easily locate and communicate with each other

## Architecture Components

### ContainerBase

The foundation class for all services providing:
- Unix socket creation and management
- Message handling interface
- Inter-service communication capabilities
- Resource cleanup on shutdown

### Service Implementations

The project includes two example services:

1. **UserService**
    - Manages user data
    - Supports operations: get, list, create

2. **OrderService**
    - Manages order data
    - Supports operations: get, list, create
    - Demonstrates inter-service communication by fetching user details

### Orchestrator

Manages the lifecycle of all services:
- Service registration and initialization
- Process forking to isolate services
- Signal handling for graceful shutdown
- Process monitoring

## Installation

1. Clone the repository
2. Ensure PHP is installed with the following extensions:
    - `pcntl`
    - `posix`
    - `sockets`

## Usage

Run the main script to start all services:

```bash
php container_architecture.php
```

To stop the application, press `Ctrl+C` for graceful shutdown.

## Example Service Communication

Services use a standard JSON-based message format:

```php
// Request
$request = [
    'action' => 'get',
    'userId' => 1
];

// Response
$response = [
    'status' => 'success',
    'data' => [
        'id' => 1,
        'name' => 'Ali',
        'email' => 'ali@example.com'
    ]
];
```

## Extending the Architecture

To add a new service:

1. Create a new class extending `ContainerBase`
2. Implement the `handleMessage()` method to process incoming messages
3. Register the service with the Orchestrator

Example:

```php
class ProductService extends ContainerBase {
    protected function handleMessage($message) {
        // Handle service-specific messages
    }
}

// Register with orchestrator
$orchestrator->registerService('ProductService');
```

## Benefits Over HTTP-based Microservices

- **Lower Latency**: Direct socket communication is faster than HTTP
- **Reduced Overhead**: No need for HTTP servers, routers, etc.
- **Simplified Authentication**: Services operate in a trusted environment
- **Resource Efficiency**: Lightweight communication suitable for resource-constrained environments

## Limitations

- Services must run on the same physical host or share a volume for socket files
- Limited to PHP applications
- Requires `pcntl` and other extensions not available in all PHP environments

```mermaid
classDiagram
    %% Core Classes
    class Config {
        -static instance: Config
        -config: array
        -__construct()
        +static getInstance(): Config
        +get(string key, default): mixed
    }

    class Logger {
        -static instance: Logger
        -logPath: string
        -serviceName: string
        -debug: bool
        -__construct(string serviceName)
        +static getInstance(string serviceName): Logger
        +info(string message, array context): void
        +error(string message, array context): void
        +debug(string message, array context): void
        -log(string level, string message, array context): void
    }

    class Security {
        -static instance: Security
        -authToken: string
        -__construct()
        +static getInstance(): Security
        -generateToken(): string
        +getToken(): string
        +validateToken(string token): bool
        +authenticateMessage(array message): bool
    }

    %% Exceptions
    class Exception

    class ServiceException {
    }

    class SocketException {
    }

    class AuthenticationException {
    }

    class TimeoutException {
    }

    %% Orchestrator
    class Orchestrator {
        #services: array
        #logger: Logger
        #running: bool
        #serviceInfo: array
        +__construct()
        -setupSignalHandlers(): void
        +handleSignal(int signal): void
        +registerService(string serviceClass, string serviceName): void
        +run(): void
        +reload(): void
        +shutdown(): void
        +getStatus(): array
    }

    %% Base Container
    class ContainerBase {
        <<abstract>>
        #socketPath: string
        #socket
        #serviceName: string
        #logger: Logger
        #running: bool
        #security: Security
        #metrics: array
        +__construct(string serviceName)
        -setupSignalHandlers(): void
        +handleSignal(int signal): void
        #createSocket(): void
        +listen(): void
        #receiveMessage($client): array
        #sendMessage($client, array data): void
        +callService(string serviceName, array data): array
        +getMetrics(): array
        #getMemoryUsage(): string
        #handleMessage(array message): array
        +__destruct()
    }

    %% Service Classes
    class UserService {
        #handleMessage(array message): array
        #getAllUsers(): array
        #getUser(int userId): array
        #createUser(array userData): array
        #updateUser(int userId, array userData): array
        #deleteUser(int userId): array
    }

    class AdminService {
        #systemStats: array
        +__construct(string serviceName)
        +updateSystemStats(): void
        #handleMessage(array message): array
        #getServicesStatus(): array
        #restartService(string serviceName): array
        #restartAllServices(): array
        #getLogs(string serviceName, int lines): array
        #getProcessList(): array
        #getEnvironmentInfo(): array
    }

    %% Relationships
    Exception <|-- ServiceException
    ServiceException <|-- SocketException
    ServiceException <|-- AuthenticationException
    ServiceException <|-- TimeoutException

    Orchestrator --> Logger : uses
    Orchestrator --> UserService : manages
    Orchestrator --> AdminService : manages

    ContainerBase <|-- UserService
    ContainerBase <|-- AdminService

    ContainerBase --> Logger : uses
    ContainerBase --> Security : uses
    ContainerBase --> Config : uses

    UserService ..> ServiceException : throws
    AdminService ..> ServiceException : throws

    Config --> Logger : dependency
    Logger --> Config : dependency

```

```mermaid
graph TB
%% Main Components
main["index.php (Main)"] --> orchestrator["Orchestrator"]

    %% Configuration and Support
    config["Config\n(Singleton)"]
    logger["Logger\n(Singleton)"]
    security["Security\n(Singleton)"]
    
    %% Services
    user_service["UserService"]
    admin_service["AdminService"]
    
    %% Socket Communication
    socket_dir["Socket Directory\n(/tmp/services/)"]
    user_socket["user.sock"]
    admin_socket["admin.sock"]
    
    %% Log Files
    log_dir["Log Directory\n(/var/log/microservices/)"]
    user_log["user.log"]
    admin_log["admin.log"]
    orchestrator_log["orchestrator.log"]
    app_log["app.log"]
    
    %% Client Connections
    client["Client"] --> user_socket
    client --> admin_socket
    
    %% Relationships
    orchestrator --> user_service
    orchestrator --> admin_service
    
    user_service --> user_socket
    admin_service --> admin_socket
    
    user_service --> user_log
    admin_service --> admin_log
    orchestrator --> orchestrator_log
    main --> app_log
    
    user_service <--> admin_service
    
    config <--> user_service
    config <--> admin_service
    config <--> orchestrator
    
    logger <--> user_service
    logger <--> admin_service
    logger <--> orchestrator
    
    security <--> user_service
    security <--> admin_service
    
    %% Signal Handling
    signals["OS Signals\n(SIGTERM, SIGINT, SIGHUP)"] --> orchestrator
    signals --> user_service
    signals --> admin_service
    
    %% Class styles
    classDef service fill:#f9f,stroke:#333,stroke-width:2px
    classDef core fill:#bbf,stroke:#333,stroke-width:2px
    classDef io fill:#bfb,stroke:#333,stroke-width:2px
    
    class user_service,admin_service service
    class config,logger,security,orchestrator core
    class socket_dir,log_dir,user_socket,admin_socket,user_log,admin_log,orchestrator_log,app_log io

```

```mermaid
graph TB
    %% Main Components
    subgraph process_layer["Process Orchestration Layer"]
        main["index.php\n(Entry Point)"]
        orchestrator["Orchestrator\n- Process Management\n- Service Registration\n- Signal Handling"]
    end
    
    subgraph core_utils["Core Utilities"]
        config["Config\n- Configuration Management\n- Singleton Pattern"]
        logger["Logger\n- Log Formatting\n- Log Storage\n- Different Log Levels"]
        security["Security\n- Authentication\n- Token Management"]
        exceptions["Exceptions\n- ServiceException\n- SocketException\n- AuthenticationException\n- TimeoutException"]
    end
    
    subgraph service_layer["Service Layer"]
        container["ContainerBase (Abstract)\n- Socket Communication\n- Service Lifecycle\n- Message Handling\n- Inter-service Communication"]
        
        subgraph services["Services"]
            user_service["UserService\n- User CRUD Operations\n- Health Checks\n- Metrics"]
            admin_service["AdminService\n- System Monitoring\n- Service Management\n- Log Viewing\n- Process Control"]
            future_services["Future Services...\n- Any new service class can\n extend ContainerBase"]
        end
    end
    
    subgraph comm_layer["Communication Layer"]
        sockets["Unix Domain Sockets\n- IPC Communication\n- JSON Messages\n- Authentication"]
    end
    
    subgraph storage_layer["Storage Layer"]
        logs["Log Files\n- Service-specific Logs\n- Error Logs\n- Debug Logs"]
        config_file["Configuration Files\n- Default Settings\n- User Overrides"]
    end
    
    %% Relationships (High Level)
    main --> orchestrator
    orchestrator --> container
    
    container --> user_service
    container --> admin_service
    container --> future_services
    
    %% Core utilities relationships
    config --> container
    logger --> container
    security --> container
    exceptions --> container
    
    config --> orchestrator
    logger --> orchestrator
    exceptions --> orchestrator
    
    %% Communication relationships
    user_service --> sockets
    admin_service --> sockets
    future_services --> sockets
    
    %% Storage relationships
    logger --> logs
    config --> config_file
    
    %% Style
    classDef main fill:#f96,stroke:#333,stroke-width:2px
    classDef service fill:#f9f,stroke:#333,stroke-width:2px
    classDef core fill:#bbf,stroke:#333,stroke-width:2px
    classDef storage fill:#bfb,stroke:#333,stroke-width:2px
    classDef communication fill:#ff9,stroke:#333,stroke-width:2px
    
    class main,orchestrator main
    class container,user_service,admin_service,future_services service
    class config,logger,security,exceptions core
    class logs,config_file storage
    class sockets communication

```


```mermaid
sequenceDiagram
    participant C as Client
    participant US as UserService
    participant AS as AdminService
    participant L as Logger
    participant S as Security
    participant CO as Config
    
    Note over C,CO: Message Flow Between Services
    
    C->>US: Connect to socket
    US->>S: authenticateMessage(message)
    S-->>US: Authentication result
    
    alt Authentication Failed
        US-->>C: Return error (401 Unauthorized)
    else Authentication Successful
        US->>US: handleMessage(message)
        US->>L: log request details
        
        alt Inter-service Communication Required
            US->>AS: callService(serviceName, data)
            AS->>S: authenticateMessage(message)
            S-->>AS: Authentication result
            AS->>AS: handleMessage(message)
            AS->>L: log request details
            AS-->>US: Return response
        end
        
        US-->>C: Return response
    end
    
    Note over C,CO: Error Handling & Logging
    
    alt Exception Occurs
        US->>L: error(message, context)
        US-->>C: Return error (500 Internal Server Error)
    end
    
    Note over C,CO: Service Initialization
    
    CO->>CO: Load configuration
    US->>CO: get configuration values
    US->>L: getInstance(serviceName)
    US->>S: getInstance()
    US->>US: createSocket()
    US->>US: listen()
```