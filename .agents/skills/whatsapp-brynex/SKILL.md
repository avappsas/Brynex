---
name: whatsapp-brynex
description: >
  Integración de WhatsApp Business API en Brynex: conversaciones, plantillas, envíos
  masivos y webhooks. Actívate cuando el usuario mencione: WhatsApp, mensajes masivos,
  plantilla WhatsApp, webhook Meta, conversación WhatsApp, WhatsappApiService,
  WhatsappWebhookService, WhatsappChatController, WhatsappMasivoController,
  envío masivo, cobros por WhatsApp.
---

# Skill: WhatsApp Brynex

## Arquitectura del Módulo

```
Controllers:
├── WhatsappChatController.php    ← Conversaciones individuales
├── WhatsappConfigController.php  ← Configuración por aliado  
├── WhatsappMasivoController.php  ← Envíos masivos
├── WhatsappPlantillaController.php ← CRUD de plantillas
└── WhatsappWebhookController.php ← Webhook Meta (mensajes entrantes)

Services:
├── WhatsappApiService.php        ← Llamadas a la API de Meta
└── WhatsappWebhookService.php    ← Procesamiento de eventos

Modelos:
├── WhatsappConfig.php            ← Config por aliado (token, número, etc.)
├── WhatsappConversacion.php      ← Hilo de conversación con un cliente
├── WhatsappMensaje.php           ← Mensajes individuales
├── WhatsappPlantilla.php         ← Plantillas aprobadas por Meta
├── WhatsappEnvioMasivo.php       ← Campaña de envío masivo
└── WhatsappEnvioMasivoDetalle.php ← Detalle por destinatario
```

## Configuración por Aliado (`WhatsappConfig`)

```php
// Columnas de configuración:
// - aliado_id
// - phone_number_id     ← ID del número en Meta
// - access_token        ← Token de acceso Meta API
// - webhook_verify_token
// - activo (boolean)
// - plantilla_cobros_id ← Plantilla para mensajes de cobro
// - campos de configuración de cobros automáticos
```

## Flujo de Envío Masivo

1. Crear `WhatsappEnvioMasivo` con selección de destinatarios
2. Generar `WhatsappEnvioMasivoDetalle` por cada destinatario
3. Procesar via Job en cola (`QUEUE_CONNECTION=sync` en local)
4. `WhatsappApiService::enviarPlantilla()` → API Meta
5. Actualizar estado: `pendiente` → `enviado` / `fallido`

## Integración con Cobros

El módulo de cobros (`CobrosController`) usa WhatsApp para:
- Enviar recordatorios de factura pendiente
- Adjuntar PDF de factura en el mensaje
- Registrar en `BitacoraCobro` el envío

## Webhook (Mensajes Entrantes)

```
POST /webhook/whatsapp
  → WhatsappWebhookController::receive()
  → WhatsappWebhookService::processMessage()
    → Busca/crea WhatsappConversacion
    → Crea WhatsappMensaje
    → Emite evento via Laravel Reverb (WebSockets)
```

## Vistas del Módulo

```
resources/views/admin/whatsapp/
├── chat/             ← Chat en tiempo real (Reverb)
├── config/           ← Configuración del número
├── masivo/           ← Gestión de envíos masivos
└── plantillas/       ← CRUD de plantillas Meta
```

## Notas Importantes

- Las plantillas deben estar **aprobadas por Meta** antes de usarse
- Los mensajes de plantilla solo aplican fuera de la ventana de 24h de conversación
- `laravel/reverb` maneja los WebSockets para el chat en tiempo real
- Índices de performance en `whatsapp_mensajes`: `conversacion_id`, `created_at`
