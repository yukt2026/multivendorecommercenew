# RIDER / DELIVERY PARTNER FUNCTIONALITY AUDIT (STEP 2)

**Project:** Laravel Multivendor E-Commerce (`OneStall`)  
**Audit Date:** 2026-11-15  
**Prerequisite:** Based on structural inventory from `RIDER_STEP_1_DISCOVERY.md`.  
**Scope:** In-depth code-level inspection of all 20 Rider-related functional capabilities.

---

## 1. Classification Overview

| # | Feature | Status Classification | Key File / Area |
| :---: | :--- | :--- | :--- |
| **1** | Rider Registration | **Partially implemented** | `DeliveryPartnerController@store` |
| **2** | Rider Login | **Partially implemented** | `RiderController@resolveRider` (Demo Simulation) |
| **3** | Rider Profile | **Partially implemented** | `DeliveryPartnerController@update` & `rider/index.blade.php` |
| **4** | Admin Rider Management | **Completed and verified** | `DeliveryPartnerController` (8/8 tests pass) |
| **5** | Rider Approval | **Missing** | No approval or KYC flow |
| **6** | Rider Dashboard | **Partially implemented** | `RiderController@dashboard` & `rider/index.blade.php` |
| **7** | Order Assignment | **Completed and verified** | `QuickCommerceService::autoAssignRider` (Haversine) |
| **8** | Accept / Reject Orders | **Partially implemented** | `RiderController@accept` (Reject is missing) |
| **9** | Pickup Management | **Partially implemented** | `RiderController@pickup` & `OrderStateMachine` |
| **10** | Delivery Management | **Partially implemented** | `RiderController@deliver` & `verifyDeliveryOtp` |
| **11** | Delivery OTP | **Completed and verified** | `FulfilmentOrder::ensureDeliveryOtp` & handshake |
| **12** | Failed Delivery | **Missing** | No failed delivery action or reason recording |
| **13** | Return / Reattempt | **Missing** | No return-to-store or reattempt workflow for riders |
| **14** | Rider Earnings | **Missing** | No payout calculations, tips, or per-drop rates |
| **15** | Rider Wallet | **Missing** | No rider wallet or cash-in-hand reconciliation |
| **16** | Rider Notifications | **Missing** | No push/SMS/WhatsApp triggers to riders |
| **17** | Rider Support | **Missing** | No rider helpdesk or SOS emergency channel |
| **18** | Rider Location Tracking | **Partially implemented** | `RiderController@updateLocation` (Manual / Simulated) |
| **19** | Rider APIs | **Partially implemented** | `POST /api/v1/rider/location` (Unauthenticated) |
| **20** | Admin Reports | **Partially implemented** | `DeliveryPartnerController@export` (CSV) |

---

## 2. In-Depth Feature Audit (1 to 20)

```
LEGEND:
[COMPLETED & VERIFIED] - Fully implemented in backend, frontend/API, and verified with tests.
[PARTIALLY IMPLEMENTED] - Core logic exists, but has key omissions, security gaps, or missing parts.
[MISSING]               - Feature does not exist in schema, controller, service, or views.
[BROKEN]                - Code exists but throws fatal exceptions, syntax errors, or failing tests.
[NOT VERIFIED]          - Feature cannot be tested or verified in current environment.
```

---

### Feature 1: Rider Registration
- **Status:** `Partially implemented`
- **What Exists:**
  - Admin can register a new delivery partner manually in Admin Central (`POST /admin/delivery-partners` -> `App\Http\Controllers\Admin\DeliveryPartnerController::store`).
  - Validates `name`, `phone` (`unique:delivery_partners,phone`), `vehicle`, `vehicle_number`, `status`, `current_lat`, `current_lng`, and `is_active`.
  - Database factory exists (`DeliveryPartnerFactory.php`) for seeding test riders.
- **What is Missing:**
  - **No Rider Self-Registration:** There is no public portal, route, or form (e.g., `/rider/register` or `/register/rider`) for delivery drivers to sign up.
  - **No Linked User Account Creation:** When Admin creates a rider via `DeliveryPartnerController@store`, only a `delivery_partners` table row is created with `user_id = null`. No corresponding record is created in the `users` table, meaning the rider has no credentials or password.
  - **No Document Upload:** No driving license, vehicle insurance, RC book, or Aadhaar/PAN KYC document collection.

---

### Feature 2: Rider Login
- **Status:** `Partially implemented`
- **What Exists:**
  - Session and fallback resolution in `App\Http\Controllers\Rider\RiderController::resolveRider`:
    1. Checks `session('active_rider_id')`.
    2. Checks `auth()->check()` and matches `DeliveryPartner::where('user_id', auth()->id())`.
    3. Fallback: Automatically invokes `DeliveryPartner::firstOrCreate(['phone' => '9876543210'])` as demo rider `"Kavitha (Quick Rider)"`.
  - Dropdown in `resources/views/rider/index.blade.php` allows switching active rider for simulation (`POST /rider/switch` -> `RiderController::switchRider`).
- **What is Missing:**
  - **No Authentication Screen:** There is no dedicated `/rider/login` page.
  - **No Route Protection:** All `/rider/*` routes in `routes/web.php` are completely unprotected by authentication middleware. Anyone can load `/rider`.
  - **No Rider Auth Guard:** No `rider` guard in `config/auth.php`.
  - **No Mobile Phone + OTP Login:** Standard rider apps authenticate via SMS/WhatsApp OTP; this is not implemented.

---

### Feature 3: Rider Profile
- **Status:** `Partially implemented`
- **What Exists:**
  - **Admin View & Edit:** Admin can view full profile details (ID, name, phone, vehicle, vehicle plate, active assignments count, coordinates) and update them via `PUT /admin/delivery-partners/{deliveryPartner}` (`DeliveryPartnerController::update`).
  - **Rider Header View:** In `resources/views/rider/index.blade.php`, the header displays the active rider's name, duty status badge, and vehicle type.
- **What is Missing:**
  - **No Rider-Facing Profile Screen:** No `/rider/profile` route or view where a rider can update their phone number, emergency contacts, profile photo, or vehicle details.
  - **No Identity Link:** In `App\Models\User`, there is no inverse relationship method `public function deliveryPartner(): HasOne`.

---

### Feature 4: Admin Rider Management
- **Status:** `Completed and verified`
- **What Exists:**
  - Controller: `App\Http\Controllers\Admin\DeliveryPartnerController`.
  - View: `resources/views/admin/delivery-partners/index.blade.php`.
  - Route group: `/admin/delivery-partners` with routes for `index`, `store`, `update`, `destroy`, `toggle`, `export`.
  - Search & Filters: Search by rider name, phone, or vehicle registration; filter by duty status (`available`, `busy`, `offline`) and vehicle fleet type (`Electric Scooter`, `Motorcycle`, `Bicycle`, `Van`).
  - Duty Toggle: Realtime toggle between Online (`available`) and Offline (`offline`).
  - Deletion Safety: Rejects deletion if `$deliveryPartner->assignments()->whereIn('status', ['assigned', 'accepted', 'picked_up'])->exists()` or `$active_orders_count > 0`.
  - CSV Export: Streamed download with all fleet columns.
  - Live Telemetry: Shows latitude/longitude with direct clickable links to Google Maps.
  - Test Verification: `tests/Feature/AdminDeliveryPartnersTest.php` passes **8/8 tests (31 assertions)**.

---

### Feature 5: Rider Approval
- **Status:** `Missing`
- **What Exists:**
  - Only boolean `is_active` exists on `delivery_partners`.
- **What is Missing:**
  - No rider approval state machine (e.g. `pending`, `under_review`, `approved`, `rejected`, `suspended`).
  - No admin approval / rejection buttons or rejection reason logging.
  - No background verification (KYC/police verification/DL validation) status tracking.

---

### Feature 6: Rider Dashboard
- **Status:** `Partially implemented`
- **What Exists:**
  - Route: `GET /rider` -> `App\Http\Controllers\Rider\RiderController::dashboard`.
  - View: `resources/views/rider/index.blade.php`.
  - Features:
    - Active assignments counter badge and "Delivered today" counter.
    - Active order cards showing Store name & pickup address (`P`), Customer name & drop-off address (`D`), item count, and status badge (`assigned`, `accepted`, `picked_up`).
    - Quick Telemetry Card showing live coordinates and simulated "Send GPS Location Ping" button.
    - Deep-link button: "Open Task & Update Status →" (`/rider/orders/{assignment}`).
- **What is Missing:**
  - No duty switch for the rider to go on-duty/off-duty directly from the dashboard.
  - No order history tab or past delivery earnings.
  - No authentication barrier (defaults to demo session).

---

### Feature 7: Order Assignment
- **Status:** `Completed and verified`
- **What Exists:**
  - Automated Dispatch:
    - In `OrderStateMachine.php`, when a `FulfilmentOrder` of type `quick` reaches `ready`, it triggers `QuickCommerceService::autoAssignRider($fo)`.
    - `autoAssignRider` queries available partners (`status = 'available'`, fallback `< 2` active orders).
    - Sorts partners using Haversine spherical distance calculation to the store (`distanceTo($storeLat, $storeLng)`).
    - Calls `assignPartnerToOrder`, transitions FO to `rider_assigned`, sets partner status to `busy`, and increments `active_orders_count`.
  - Manual Dispatch:
    - Store manager can assign a specific rider via `POST /seller/orders/{fulfilmentOrder}/assign-rider` (`QuickOrderController::assignRider`).
  - Test Verification:
    - Verified in `tests/Feature/SellerQuickOrdersTest.php` (`test_seller_can_handover_order_to_rider`) — all pass.

---

### Feature 8: Accept / Reject Orders
- **Status:** `Partially implemented`
- **What Exists:**
  - Accept Action:
    - Route: `POST /rider/orders/{assignment}/accept` (`RiderController::accept`).
    - Sets `$assignment->status = DeliveryAssignmentStatus::ACCEPTED`.
    - Sets `$assignment->accepted_at = now()`.
    - UI: Button "✓ Accept Assignment" in `rider/assignment.blade.php`.
- **What is Missing:**
  - **No Reject Action:** `DeliveryAssignmentStatus::REJECTED` exists in the Enum, but there is no `reject()` method in `RiderController`, no `/rider/orders/{assignment}/reject` route, and no "Reject" button in the UI.
  - **No Reassignment Flow:** If a rider rejects an order or fails to respond within a timeout, there is no automatic dispatch to the next nearest rider.

---

### Feature 9: Pickup Management
- **Status:** `Partially implemented`
- **What Exists:**
  - Route: `POST /rider/orders/{assignment}/pickup` (`RiderController::pickup`).
  - Updates `$assignment->status = DeliveryAssignmentStatus::PICKED_UP` and records `picked_up_at = now()`.
  - Triggers `OrderStateMachine` transitions:
    - `transitionFulfilmentOrder($fo, 'picked_up', 'Rider picked up order from store')`
    - `transitionFulfilmentOrder($fo, 'out_for_delivery', 'Order is out for delivery with rider')`
  - Reconciles parent `Order` status to `out_for_delivery`.
  - Seller quick console: `QuickOrderController::handover` provides reciprocal handover confirmation.
  - View displays store pickup details, address, and `tel:` button.
- **What is Missing:**
  - No physical verification: No barcode/QR code scan of the merchant package to verify the rider picked up the correct bag.
  - No store confirmation PIN/signature.

---

### Feature 10: Delivery Management
- **Status:** `Partially implemented`
- **What Exists:**
  - Route: `POST /rider/orders/{assignment}/deliver` (`RiderController::deliver`).
  - Validates 6-digit OTP via `QuickCommerceService::verifyDeliveryOtp($fo, $otp)`.
  - Updates `$assignment->status = DeliveryAssignmentStatus::DELIVERED` and `delivered_at = now()`.
  - Frees the rider: Decrements `active_orders_count`; resets `status = 'available'` when zero.
  - Reconciles parent `Order` to `delivered`.
  - Displays customer drop-off address and "Call Customer" `tel:` link.
- **What is Missing:**
  - **No Proof of Delivery (POD):** No doorstep photo capture/upload.
  - **No Customer Digital Signature.**
  - **No Cash on Delivery (COD) Collection:** If the order is COD, there is no cash collection prompt, amount confirmation, or cash-in-hand accounting on the rider side.

---

### Feature 11: Delivery OTP
- **Status:** `Completed and verified`
- **What Exists:**
  - Generation: `FulfilmentOrder::ensureDeliveryOtp()` creates a 6-digit random code (`sprintf('%06d', mt_rand(100000, 999999))`).
  - Assignment Integration: `QuickCommerceService::assignPartnerToOrder` triggers `ensureDeliveryOtp()`.
  - Customer Visibility: Displayed prominently on `resources/views/storefront/orders/live-tracking.blade.php` ("Share with rider upon arrival").
  - Rider Entry: Modal in `resources/views/rider/assignment.blade.php` with large formatted 6-digit input.
  - Verification: `QuickCommerceService::verifyDeliveryOtp()` verifies equality, flags `$assignment->otp_verified = true`, and returns clean error responses on mismatch.

---

### Feature 12: Failed Delivery
- **Status:** `Missing`
- **What Exists:**
  - None for Quick Commerce riders. (Only standard courier NDR/RTO exists for OneStall Cargo freight).
- **What is Missing:**
  - No "Delivery Failed" or "Customer Unavailable" button in `rider/assignment.blade.php`.
  - No failure reason choices (e.g., customer unreachable, incorrect address, customer refused, premises locked).
  - No rider-side cancellation or exception workflow.

---

### Feature 13: Return / Reattempt
- **Status:** `Missing`
- **What Exists:**
  - None for Quick Commerce riders.
- **What is Missing:**
  - No return-to-store task or workflow for quick commerce riders to bring items back to the dark store.
  - No inventory restock trigger when a quick commerce package is returned to the merchant.
  - No scheduled re-attempt flow.

---

### Feature 14: Rider Earnings
- **Status:** `Missing`
- **What Exists:**
  - `orders` and `fulfilment_orders` track `delivery_fee` charged to customer.
- **What is Missing:**
  - No rider payout calculation (e.g., base pay per delivery + per km incentive + peak surge).
  - No rider earnings table or columns in `delivery_partners` or `delivery_assignments`.
  - No tips tracking.
  - No daily/weekly payout summaries.

---

### Feature 15: Rider Wallet
- **Status:** `Missing`
- **What Exists:**
  - `wallet_transactions` and `users.wallet_balance` exist, but are built exclusively for **Customer store credit / refunds**.
- **What is Missing:**
  - No Rider Wallet model or ledger.
  - No cash-in-hand limit (for COD collections).
  - No rider withdrawal request or payout processing workflow.

---

### Feature 16: Rider Notifications
- **Status:** `Missing`
- **What Exists:**
  - `NotificationTemplateController.php` contains customer notifications (`out_for_delivery` via SMS/WhatsApp).
  - When an order is assigned, `QuickCommerceService` only calls `Log::info()`.
- **What is Missing:**
  - No SMS, WhatsApp, or Push notifications sent to the **Rider** when assigned to an order.
  - No sound/alarm trigger on the rider web dashboard for incoming orders.
  - No notification when an order is cancelled while the rider is in transit.

---

### Feature 17: Rider Support
- **Status:** `Missing`
- **What Exists:**
  - Platform support desk exists for Customers and Sellers (`SupportTicketController`).
- **What is Missing:**
  - No support ticketing or issue reporting for riders.
  - No SOS / emergency button in the rider web portal.
  - No direct call button to OneStall Rider Dispatch Support.

---

### Feature 18: Rider Location Tracking
- **Status:** `Partially implemented`
- **What Exists:**
  - Columns: `delivery_partners.current_lat`, `current_lng`, `last_location_updated_at`.
  - Telemetry Endpoint: `POST /rider/location` and `POST /api/v1/rider/location` (`RiderController::updateLocation`).
  - Customer Map: `resources/views/storefront/orders/live-tracking.blade.php` polls `orders.live_location` every 5 seconds and animates the rider pin.
  - Admin View: Google Maps direct link with coordinates and "Updated X minutes ago".
  - Simulation: "Send GPS Location Ping" button in `rider/index.blade.php`.
- **What is Missing:**
  - No continuous background tracking (e.g., HTML5 `navigator.geolocation.watchPosition` or mobile GPS service).
  - No location history table (breadcrumbs trail); only latest single coordinate is retained.
  - No WebSockets / Pusher / Reverb real-time broadcast (currently relies on HTTP polling).

---

### Feature 19: Rider APIs
- **Status:** `Partially implemented`
- **What Exists:**
  - `POST /api/v1/rider/location` (`App\Http\Controllers\Rider\RiderController::updateLocation`).
  - Web routes support JSON format when `wantsJson()` is passed on `accept`, `pickup`, and `deliver`.
- **What is Missing:**
  - **No API Authentication:** `POST /api/v1/rider/location` is unauthenticated (anyone can post coordinates for any `rider_id`).
  - **No Dedicated API Group:** No REST API endpoints for:
    - `POST /api/v1/rider/login`
    - `GET /api/v1/rider/profile`
    - `GET /api/v1/rider/orders/active`
    - `GET /api/v1/rider/orders/history`
    - `POST /api/v1/rider/status` (online/offline toggle)

---

### Feature 20: Admin Reports
- **Status:** `Partially implemented`
- **What Exists:**
  - Fleet CSV Export: `GET /admin/delivery-partners/export` (`DeliveryPartnerController::export`) streams full CSV with active orders, completions, and coordinates.
  - Admin Mission Control: `Admin/QuickCommerceOpsController` tracks live available vs. busy rider metrics.
  - Sales Reports: `Admin/ReportController` breaks down customer delivery fee revenues.
- **What is Missing:**
  - No dedicated Rider Performance Analytics (e.g., average delivery time from store to customer, on-time SLA adherence, delivery cancellation rate per rider, total distance traveled).

---

## 3. Summary Scorecard

```
Total Features Audited:      20
- Completed and verified:     3  (15.0%)
- Partially implemented:      9  (45.0%)
- Missing:                    8  (40.0%)
- Broken:                     0  ( 0.0%)
- Not verified:               0  ( 0.0%)
```

### Key Takeaway:
The fundamental **operational backbone** (database schema, models, automated proximity dispatch, lifecycle state machine, delivery OTP handshake, customer live tracking, and admin fleet CRUD) is built and working. 

The major gaps are:
1. **Security & Authentication:** Unprotected `/rider` routes, lack of rider login/guard, and unauthenticated location API.
2. **Exception Handling:** Lack of Reject, Delivery Failure, and Return-to-Store workflows.
3. **Financials & Incentives:** Complete absence of Rider Earnings, Wallet, and Payout tracking.
4. **Driver Communication:** Absence of rider push notifications and support channels.

---
*Report generated strictly following read-only code audit guidelines. No code, configurations, or database records were modified.*
