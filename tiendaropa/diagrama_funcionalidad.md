# Diagrama de Funcionalidad Completa - FashionHub (TiendaRopa)

## Flujo General del Sitio Web

```mermaid
flowchart TB
    subgraph USUARIO["👤 USUARIO"]
        direction TB
        A[Visita sitio web] --> B{¿Tiene cuenta?}
        B -->|No| C[Registro<br/>register.php]
        B -->|Sí| D[Login<br/>login.php]
        C --> D
        D --> E[Catálogo de Productos<br/>index.php]
    end

    subgraph COMPRAS["🛒 PROCESO DE COMPRA"]
        direction TB
        E --> F[Ver Producto]
        F --> G[Agregar al Carrito<br/>cart.php]
        G --> H{¿Listo para pagar?}
        H -->|No| F
        H -->|Sí| I[Checkout<br/>checkout.php]
    end

    subgraph PAGO["💳 FLUJO DE PAGO CONEKTA"]
        direction TB
        I --> J[Ingresar datos tarjeta]
        J --> K[Conekta.js genera Token]
        K --> L[Enviar Token al servidor]
        L --> M[process_payment.php<br/>Envía orden a API Conekta]
        M --> N{Respuesta Conekta}
        N -->|paid| O[Guardar ticket + items<br/>status: paid<br/>conekta_status: paid]
        N -->|rejected| P[Guardar ticket + items<br/>status: rejected<br/>conekta_status: declined]
        O --> Q[Redirigir a ticket.php]
        P --> Q
    end

    subgraph WEBHOOK["📡 WEBHOOK CONEKTA"]
        direction TB
        W1[Conekta envía evento POST<br/>webhook_conekta.php]
        W1 --> W2{Tipo de evento válido?}
        W2 -->|No| W3[Responder 200 OK]
        W2 -->|Sí| W4[Extraer order_id<br/>y payment_status]
        W4 --> W5[Buscar ticket por<br/>conekta_order_id]
        W5 --> W6[Actualizar conekta_status<br/>y status en BD]
        W6 --> W7{¿Rechazado?}
        W7 -->|Sí| W8[Restaurar stock<br/>de productos]
        W7 -->|No| W9[Responder 200 OK]
        W8 --> W9
    end

    subgraph POLLING["🔄 JS POLLING (5 seg)"]
        direction TB
        P1[payment_status.js<br/>Inicia polling]
        P1 --> P2[Consultar check_status.php<br/>cada 5 segundos]
        P2 --> P3{¿Cambió el estatus?}
        P3 -->|No| P4[No ejecutar acción<br/>Solo repetir]
        P3 -->|Sí| P5[Incrementar contador<br/>Actualizar UI]
        P4 --> P2
        P5 --> P6{¿Estatus final?}
        P6 -->|No| P2
        P6 -->|Sí| P7[Detener polling<br/>Mostrar estado final]
    end

    subgraph ADMIN["⚙️ PANEL ADMIN"]
        direction TB
        AD1[Login como admin] --> AD2[Panel admin.php]
        AD2 --> AD3[Ver usuarios]
        AD2 --> AD4[Ver productos]
        AD2 --> AD5[Agregar producto]
        AD2 --> AD6[Editar producto]
        AD2 --> AD7[Eliminar producto]
    end

    subgraph BD["🗄️ BASE DE DATOS SQLite"]
        direction LR
        DB1[(users)]
        DB2[(products)]
        DB3[(tickets<br/>+ conekta_order_id<br/>+ conekta_status)]
        DB4[(ticket_items)]
    end

    Q --> P1
    W6 -.->|Actualiza| DB3
    P2 -.->|Consulta| DB3
    M -.->|Inserta| DB3
    M -.->|Inserta| DB4

    style WEBHOOK fill:#fff3cd,stroke:#ffc107
    style POLLING fill:#d1ecf1,stroke:#17a2b8
    style PAGO fill:#d4edda,stroke:#28a745
    style ADMIN fill:#f8d7da,stroke:#dc3545
    style BD fill:#e2e3e5,stroke:#6c757d
```

## Flujo del Webhook + Polling (Detallado)

```mermaid
sequenceDiagram
    participant U as 👤 Usuario
    participant B as 🌐 Browser
    participant S as 🖥️ Servidor PHP
    participant C as 💳 Conekta API
    participant DB as 🗄️ SQLite DB
    participant JS as 📡 payment_status.js

    Note over U,JS: FASE 1: Proceso de Pago

    U->>B: Llena datos de tarjeta
    B->>C: Conekta.js → Token
    C-->>B: Token generado
    B->>S: POST process_payment.php (token)
    S->>C: Crear orden (API Conekta)
    C-->>S: Response (order_id, payment_status)
    S->>DB: INSERT ticket (conekta_order_id, conekta_status)
    S-->>B: Redirect → ticket.php

    Note over U,JS: FASE 2: Polling JS (cada 5 seg)

    B->>JS: Inicia polling automático
    
    loop Cada 5 segundos
        JS->>S: GET check_status.php?ticket_id=X
        S->>DB: SELECT conekta_status FROM tickets
        DB-->>S: conekta_status actual
        S-->>JS: JSON {conekta_status, change_count, is_final}
        
        alt Sin cambios (contador = 0)
            JS->>JS: No ejecutar acción, solo repetir
        else Cambio detectado (contador > 0)
            JS->>B: Actualizar UI con nuevo estatus
        end
        
        alt Estatus final alcanzado
            JS->>JS: Detener polling ✔
        end
    end

    Note over U,JS: FASE 3: Webhook (async, desde Conekta)

    C->>S: POST webhook_conekta.php (evento)
    S->>DB: UPDATE tickets SET conekta_status, status
    
    Note right of DB: En el siguiente ciclo de<br/>polling, JS detectará el<br/>cambio y actualizará la UI
```

## Estructura de Archivos

| Archivo | Tipo | Descripción |
|---------|------|-------------|
| `index.php` | Página | Catálogo de productos |
| `login.php` | Página | Inicio de sesión |
| `register.php` | Página | Registro de usuarios |
| `cart.php` | Página | Carrito de compras |
| `checkout.php` | Página | Formulario de pago Conekta |
| `process_payment.php` | Backend | Procesa pago y guarda orden con datos Conekta |
| `ticket.php` | Página | Recibo con estatus Conekta en tiempo real |
| `webhook_conekta.php` | **Webhook** | Recibe notificaciones de Conekta |
| `check_status.php` | **API** | Endpoint para polling JS |
| `payment_status.js` | **JS** | Polling cada 5s con contador de cambios |
| `admin.php` | Admin | Panel de administración |
| `db.php` | Config | Conexión SQLite |
| `schema.sql` | DB | Esquema de base de datos |
| `style.css` | CSS | Estilos globales |
