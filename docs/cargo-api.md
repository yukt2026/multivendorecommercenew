# OneStall Cargo API Specification & Integration Contract

## 1. Overview
**OneStall Cargo** is an independent logistics orchestration and freight transport platform powering:
- Express air, surface, and national linehaul delivery.
- Automated Air Waybill (AWB) generation and carrier selection.
- Warehouse carrier pickups and thermal label formatting.
- Real-time milestone tracking.
- Non-Delivery Report (NDR) exception handling and automated Return to Origin (RTO).
- Cash on Delivery (COD) cash collection and remittance ledger reconciliation.

---

## 2. Authentication & Request Signing
All outbound requests from OneStall Ecommerce to OneStall Cargo are authenticated via Bearer token and verified with an HMAC-SHA256 cryptographic signature.

### Request Headers
| Header | Description | Example |
| :--- | :--- | :--- |
| `Authorization` | Bearer API Key | `Bearer osc_live_sec123...` |
| `X-Cargo-Timestamp` | Unix timestamp (seconds) | `1799124000` |
| `X-Cargo-Signature` | `hash_hmac('sha256', timestamp . '.' . json_body, api_secret)` | `a3b89...` |
| `Content-Type` | MIME payload type | `application/json` |
| `Accept` | Target response format | `application/json` |

---

## 3. Core API Endpoints

### 3.1 Create Shipment Consignment
`POST /v1/shipments`

#### Request Body
```json
{
  "shipment_number": "SHP-20261105-X9A21C",
  "fulfilment_order_number": "FO-20261105-01",
  "origin": {
    "name": "OneStall Dark Store #01",
    "phone": "9876543210",
    "line1": "Plot 45, 100ft Road, Indiranagar",
    "city": "Bengaluru",
    "state": "Karnataka",
    "pincode": "560038"
  },
  "destination": {
    "name": "Aditi Sharma",
    "phone": "9876543210",
    "line1": "Flat 204, Green Heights, MG Road",
    "city": "Bengaluru",
    "state": "Karnataka",
    "pincode": "560001"
  },
  "weight_kg": 1.25,
  "is_cod": true,
  "cod_amount": 2999.00,
  "courier": "OneStall Express Surface",
  "service": "surface",
  "items_count": 2,
  "declared_value": 2999.00
}
```

#### Response (`201 Created`)
```json
{
  "success": true,
  "shipment_id": "CARGO-68A1B90C",
  "shipment_number": "SHP-20261105-X9A21C",
  "awb": "OSC-20261105-TRK992",
  "courier": "OneStall Express Surface",
  "service": "surface",
  "status": "manifest_created",
  "label_url": "https://cargo.onestall.internal/labels/OSC-20261105-TRK992.pdf",
  "charges": 145.00,
  "created_at": "2026-11-05T03:15:00Z"
}
```

---

### 3.2 Generate / Assign Air Waybill (AWB)
`POST /v1/shipments/{shipment_number}/awb`

#### Request Body
```json
{
  "courier": "OneStall Express Surface"
}
```

#### Response (`200 OK`)
```json
{
  "success": true,
  "shipment_number": "SHP-20261105-X9A21C",
  "awb": "OSC-20261105-TRK992",
  "courier": "OneStall Express Surface",
  "label_url": "https://cargo.onestall.internal/labels/OSC-20261105-TRK992.pdf",
  "assigned_at": "2026-11-05T03:16:00Z"
}
```

---

### 3.3 Courier Rates & SLA Calculation
`GET /v1/rates?origin_pincode=560038&dest_pincode=560001&weight_kg=1.5&cod_amount=2999`

#### Response (`200 OK`)
```json
[
  {
    "courier_code": "onestall_express",
    "courier_name": "OneStall Express Air",
    "service_type": "air",
    "rate": 180.00,
    "estimated_days": 2,
    "etd": "07 Nov 2026",
    "is_recommended": true
  },
  {
    "courier_code": "onestall_surface",
    "courier_name": "OneStall Cargo Surface",
    "service_type": "surface",
    "rate": 107.50,
    "estimated_days": 4,
    "etd": "09 Nov 2026",
    "is_recommended": false
  }
]
```

---

### 3.4 Request Carrier Pickup
`POST /v1/pickups`

#### Request Body
```json
{
  "shipment_number": "SHP-20261105-X9A21C",
  "awb": "OSC-20261105-TRK992",
  "pickup_pincode": "560038",
  "pickup_address": "Indiranagar Dark Store #01",
  "contact_phone": "9876543210",
  "pickup_date": "2026-11-06",
  "time_slot": "14:00 - 18:00"
}
```

#### Response (`200 OK`)
```json
{
  "success": true,
  "pickup_token": "PCK-20261106-9A10X",
  "status": "pickup_scheduled",
  "scheduled_date": "2026-11-06",
  "time_slot": "14:00 - 18:00",
  "message": "Carrier pickup successfully scheduled."
}
```

---

### 3.5 Live Milestone Tracking
`GET /v1/track/{awb}`

#### Response (`200 OK`)
```json
{
  "success": true,
  "awb": "OSC-20261105-TRK992",
  "current_status": "in_transit",
  "location": "Bengaluru Regional Hub",
  "estimated_delivery": "2026-11-07T18:00:00Z",
  "events": [
    {
      "status": "in_transit",
      "activity": "Consignment in transit between sorting hubs",
      "location": "Bengaluru Regional Hub",
      "timestamp": "2026-11-05T08:30:00Z"
    },
    {
      "status": "picked_up",
      "activity": "Package picked up from seller fulfillment center",
      "location": "Indiranagar Hub, Bengaluru",
      "timestamp": "2026-11-05T04:00:00Z"
    },
    {
      "status": "manifest_created",
      "activity": "Electronic cargo manifest and waybill registered",
      "location": "System",
      "timestamp": "2026-11-05T03:15:00Z"
    }
  ]
}
```

---

### 3.6 Non-Delivery Report (NDR) Instructions
`POST /v1/ndr/{awb}/action`

#### Request Body
```json
{
  "action": "reattempt",
  "remarks": "Spoke to customer; re-attempt scheduled for tomorrow morning."
}
```

#### Response (`200 OK`)
```json
{
  "success": true,
  "awb": "OSC-20261105-TRK992",
  "action": "reattempt",
  "status": "reattempt_scheduled",
  "remarks": "Action instruction transmitted to carrier.",
  "action_taken_at": "2026-11-05T10:00:00Z"
}
```

---

### 3.7 COD Remittance Data
`GET /v1/cod/remittances`

#### Response (`200 OK`)
```json
[
  {
    "remittance_id": "REMIT-908123",
    "utr_reference": "UTR-HDFC-20261105-992120",
    "shipment_awb": "OSC-20261105-TRK992",
    "collected_amount": 2999.00,
    "remitted_amount": 2964.00,
    "courier_handling_fee": 35.00,
    "status": "remitted",
    "collected_at": "2026-11-04T12:00:00Z",
    "remitted_at": "2026-11-05T09:00:00Z"
  }
]
```

---

## 4. Webhook Specifications

### 4.1 Webhook Delivery & Security
- **Target URL**: `POST /api/v1/cargo/webhook`
- **Signature Header**: `X-Cargo-Signature`
- **Validation**:
  ```php
  $expected = hash_hmac('sha256', $request->getContent(), $webhookSecret);
  hash_equals($expected, $request->header('X-Cargo-Signature'));
  ```

### 4.2 Webhook Events & Lifecycle Mapping

| Event Name | Meaning | Shipment Status | Order Action |
| :--- | :--- | :--- | :--- |
| `shipment.picked_up` | Courier scanned pickup from seller | `picked_up` | Transitions FO to `picked_up` |
| `shipment.in_transit` | In transit via hub / linehaul | `in_transit` | Transitions FO to `in_transit` |
| `shipment.out_for_delivery` | Package out with delivery agent | `out_for_delivery` | Transitions FO to `out_for_delivery` |
| `shipment.delivered` | Successful doorstep delivery | `delivered` | Transitions FO to `delivered`, triggers commission ledger, logs COD collection |
| `shipment.ndr` | Delivery attempt failed | `ndr_raised` | Creates `NdrCase` for customer/seller resolution |
| `shipment.rto_initiated` | Package marked Return to Origin | `rto_initiated` | Creates `RtoCase` |
| `shipment.rto_delivered` | Package returned to seller origin | `rto_delivered` | Closes `RtoCase` and restocks inventory |
| `cod.remitted` | Courier remitted collected cash | Status unchanged | Marks `CodRemittance` as `remitted` with bank UTR |
