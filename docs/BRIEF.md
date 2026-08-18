# Project Brief — School IT Resource & Exam Laptop Booking System

> This is the original design brief that shaped Kitloan. It's kept here for background on *why* the
> application is modelled the way it is; day-to-day setup and operation is covered in the
> [top-level README](../README.md). Some example values below (email addresses, URLs) are illustrative
> placeholders, not a real deployment's configuration.

Build a production-ready, self-contained, Docker-deployable web application for managing bookings of school IT resources.

The immediate use case is approximately **20 Exam Laptops** that teachers and authorised staff can reserve for exams, but the application must be designed as a **generic IT Resource Booking System**, rather than being hard-coded specifically around exam laptops.

Future resource pools might include:

* Exam Laptops
* Loan Laptops
* Chargers
* Portable Monitors
* Cameras
* AV equipment
* Other manually managed IT equipment

The application will initially be deployed internally for a school, but its architecture should remain reasonably generic and reusable.

---

# 1. Core Design Principles

The application should:

* Be easy and highly visual for ordinary staff.
* Require authentication through OpenID Connect/OIDC.
* Support Microsoft Entra ID as an OIDC provider without hard-coding the application specifically to Microsoft.
* Be responsive and usable on desktop, tablets and phones.
* Be installable through Docker Compose.
* Have a modern, clean web interface.
* Be maintainable without unnecessary architectural complexity.
* Use resource pools rather than hard-coding exam laptops.
* Support both Snipe-IT sourced assets and manually-created resources.
* Keep a complete booking and administrative audit trail.
* Prevent double-bookings and race conditions at the database/application level.
* Continue functioning if an external integration such as Snipe-IT is temporarily unavailable.

The system is primarily being used by authenticated school staff.

It is acceptable for authenticated users to see booking information including:

* staff member making the booking
* room
* student name, where entered
* exam type
* date and time
* number/resources booked

Administrative users may see all information.

---

# 2. Preferred Technology Stack

Use a current supported Laravel release and associated supported PHP release.

Preferred stack:

* Laravel
* PHP
* PostgreSQL as the default database
* MySQL/MariaDB support through Laravel database configuration
* Laravel migrations
* Nginx
* PHP-FPM
* Blade
* Livewire and/or Alpine.js for interactive components
* Tailwind CSS or an equivalent maintained UI framework
* Docker
* Docker Compose

Avoid building a completely separate React/Vue SPA unless there is a compelling architectural reason.

This application should remain relatively lightweight.

Redis may be supported for queues/cache but should not be mandatory for basic operation unless necessary.

Background processing should use Laravel queues where appropriate, particularly for:

* email notifications
* calendar notifications
* Snipe-IT synchronisation

The application should support a database selected through environment configuration.

Example:

```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=resource_booking
DB_USERNAME=resource_booking
DB_PASSWORD=...
```

---

# 3. Containerisation

Provide a Docker-based deployment.

At minimum provide:

```text
docker-compose.yml
Dockerfile
.env.example
README.md
```

The solution should be able to be deployed approximately as:

```bash
cp .env.example .env
docker compose up -d
```

Document:

* installation
* initial administrator creation
* OIDC configuration
* database configuration
* SMTP configuration
* Snipe-IT integration
* backup procedure
* upgrade procedure

Persistent data must use Docker volumes where appropriate.

The application itself should be stateless enough that containers can be recreated without losing application data.

---

# 4. Authentication — OpenID Connect

Authentication must use **OpenID Connect**.

Microsoft Entra ID will initially be the identity provider, but implementation should use generic OIDC wherever possible.

Configuration should include values similar to:

```env
OIDC_ENABLED=true
OIDC_ISSUER=
OIDC_CLIENT_ID=
OIDC_CLIENT_SECRET=
OIDC_REDIRECT_URI=
```

Support optional restrictions such as:

```env
OIDC_ALLOWED_DOMAINS=example.edu.au
```

Do not put OIDC secrets into ordinary database-backed web settings.

Secrets belong in:

* environment variables
* Docker secrets
* another appropriate secure secret mechanism

---

# 5. Local User Records

Although authentication occurs through OIDC, maintain a local application user table.

Suggested structure:

```text
users

id
oidc_subject
email
display_name
role
enabled
first_login_at
last_login_at
created_at
updated_at
```

Users may be created manually before their first OIDC login.

For example, an administrator may add:

```text
jane.smith@example.edu.au
Jane Smith
Role: User
```

When Jane subsequently authenticates through OIDC, associate the returned OIDC identity with the existing local account.

Account matching should primarily use the immutable OIDC subject once established.

Take care when initially matching accounts by email.

Do not silently allow an identity collision to take over an existing account.

---

# 6. Application Roles

Initially implement at least:

## User

Can:

* view resource availability
* view bookings
* create bookings
* view their own bookings
* modify their own eligible future bookings
* cancel their own eligible bookings

## IT Operator

Can:

* see all bookings
* see student details
* approve bookings
* reject bookings
* change allocations
* substitute resources
* create bookings on behalf of users
* manage operational booking information
* mark equipment unavailable

## Administrator

Can perform all IT Operator actions plus:

* manage users
* manage administrators/operators
* manage resource pools
* manage individual resources
* manage rooms
* manage booking/exam types
* manage integrations
* manage application settings
* override conflicts
* view audit logs

Implement permissions so additional roles can reasonably be introduced later.

---

# 7. Resource Pool Model

The major architectural model should be a **Resource Pool**.

Example:

```text
Resource Pool:
Exam Laptops

Description:
Laptops prepared for student examinations.

Enabled:
Yes
```

A resource pool should support configuration including:

```text
name
slug
description
enabled
icon
display_order

allocation_mode
minimum_lead_time
preparation_buffer
return_buffer

allow_weekends
allow_out_of_hours

requires_room
allows_student
requires_student
requires_booking_type

auto_approval_enabled
approval_rules
```

---

# 8. Allocation Modes

Resource pools should support at least two modes.

## Individually Tracked

Example:

```text
Exam Laptops

Laptop 01
Laptop 02
Laptop 03
...
```

Each item has its own identity and availability.

## Quantity Tracked

Example:

```text
Generic USB-C Chargers

Available Quantity: 30
```

Individual chargers do not need to have an asset record unless desired.

This allows resources that are not individually tracked in Snipe-IT to still be booked.

---

# 9. Resource Sources

Resources should have a source:

```text
manual
snipeit
```

Potentially design this generically enough to allow additional sources later.

A manually managed resource may look like:

```text
Name: USB-C Charger 01
Resource Pool: Chargers
Asset Number: optional
Serial: optional
Source: Manual
```

A Snipe-IT-backed resource might look like:

```text
Name: Exam Laptop 03
Resource Pool: Exam Laptops

Source: Snipe-IT
Snipe Asset ID: 438
Asset Tag: 10562
Serial: 5CD123456
Model: HP ProBook ...
```

---

# 10. Snipe-IT Integration

Implement optional Snipe-IT integration using the official Snipe-IT REST API.

Snipe-IT connection configuration should include:

```env
SNIPEIT_ENABLED=true
SNIPEIT_URL=https://assets.example.edu.au
SNIPEIT_API_TOKEN=
```

The API token must remain a protected secret and should not be exposed to ordinary users or rendered back to administrators after configuration.

Provide an Administration area:

**Administration → Integrations → Snipe-IT**

Show:

```text
Status: Connected

Server: assets.example.edu.au

Last successful sync:
18 August 2026 20:45

[ Test Connection ]
[ Synchronise Now ]
[ Import Assets ]
```

---

# 11. Selecting Snipe-IT Assets for a Pool

Do NOT automatically import the entire Snipe-IT inventory into the booking application.

Instead provide:

**Resource Pools → Exam Laptops → Add Resources → Import from Snipe-IT**

The administrator should be able to search/filter Snipe-IT inventory.

For example:

```text
Search assets: [________________]

☐ Asset 10021 — HP ProBook 440 — SN ABC123
☑ Asset 10023 — HP ProBook 440 — SN ABC125
☑ Asset 10024 — HP ProBook 440 — SN ABC126
☐ Asset 10025 — Dell Latitude — SN DEF128

Selected: 2

[ Add Selected Assets ]
```

Useful search/filter criteria should include where available:

* asset tag
* name
* serial
* model
* category
* status
* location

An administrator can select one or many assets and allocate them to a booking resource pool.

---

# 12. Snipe-IT Data Synchronisation

Once linked, retain:

```text
external_source = snipeit
external_id
asset_tag
serial
name
model
status
last_synced_at
external_metadata
```

Store a local snapshot so application availability and bookings are not dependent on Snipe-IT responding on every page load.

Periodically synchronise linked assets.

Allow an administrator to manually run a sync.

If the Snipe-IT API is unavailable:

* do not break the booking system
* continue using locally cached information
* display integration status to administrators
* retry synchronisation later
* log the failure

Never delete historical booking records just because an asset disappears from Snipe-IT.

Instead mark the link/resource appropriately.

---

# 13. Snipe-IT and Booking State

Treat the booking application's reservation state separately from Snipe-IT's normal asset checkout state unless explicitly configured otherwise.

A future booking:

```text
Laptop 10023
Thursday
10:00–11:00
```

should NOT necessarily mean the asset is immediately checked out in Snipe-IT.

Snipe-IT remains the authoritative asset inventory.

The booking application is authoritative for future reservations.

Design an integration point so a future enhancement could:

* check an asset out in Snipe-IT at collection time
* check it into Snipe-IT at return time

but do not make this mandatory for the initial implementation.

---

# 14. Resource Status

Local resource states should include:

```text
Available
Unavailable
Maintenance
Missing
Retired
Disabled
```

Snipe-IT asset status should also be synchronised/displayed where relevant.

If an asset becomes unavailable/retired in Snipe-IT, flag this prominently.

Do not silently cancel existing future reservations.

Instead alert IT that future bookings require reassignment.

---

# 15. Primary User Experience

The ordinary staff workflow must be visual.

Homepage after login:

```text
Resource Booking

[ Exam Laptops ]
[ Loan Laptops ]
[ Chargers ]
[ Other Resource Pools ]
```

Selecting **Exam Laptops** opens the booking interface.

---

# 16. Booking Screen

The main screen should approximately contain:

```text
Exam Laptop Booking

Date
[ 20/08/2026 ]

Start
[ 10:17 ]

Finish
[ 11:43 ]

Room
[ B12 ▼ ]

Exam Type
[ Safe Exam Browser ▼ ]

Student
[ Optional student name ]

Notes
[ Optional ]

------------------------------------------

Available Exam Laptops

[💻01] [💻02] [💻03] [▒04] [💻05]
[💻06] [▒07] [💻08] [💻09] [💻10]
[💻11] [💻12] [💻13] [💻14] [💻15]
[💻16] [💻17] [▒18] [💻19] [💻20]

Selected: 6

[ Book Selected Laptops ]
```

Make this attractive and modern.

Use an open/free laptop icon or locally supplied icon rather than depending upon an externally loaded image at runtime.

A potential inspiration is:

https://openiconlibrary.com/packs/small-flat-vectors/device-laptop-06c6c5

Do not create a runtime dependency on this URL.

---

# 17. Resource Grid Behaviour

Resource cards should clearly display their status.

For example:

**Available**

```text
💻
03
Available
```

**Selected**

Clearly highlighted.

**Unavailable**

Greyed out and non-selectable.

Where appropriate, hovering/clicking an unavailable laptop should display:

```text
Unavailable
10:00–11:30
Room B14
Booked by Jane Smith
Student: John Smith
Exam: Safe Exam Browser
```

Authenticated users may see these booking details.

Admins/operators should have access to full details and direct links to the booking.

---

# 18. Time Selection

Bookings must allow start and end times to the **minute**, not only predefined class periods.

For example:

```text
Start: 10:17
Finish: 11:43
```

Do not restrict bookings to 15/30/60 minute intervals.

Provide convenient time controls, but retain minute precision.

---

# 19. School Hours and Weekends

School hours should be configurable.

Example:

```text
Normal Day Start: 07:00
Normal Day Finish: 17:00
```

However:

* bookings outside these times must still be possible where permitted
* weekends must be supported

The administrator should be able to configure whether:

* weekends are automatically permitted
* weekend bookings require approval
* outside-hours bookings require approval

---

# 20. Rooms / Locations

Rooms must be centrally managed rather than free-form wherever possible.

Administration:

**Administration → Locations**

Suggested fields:

```text
id
code
name
campus
building
description
enabled
display_order
```

Example:

```text
Code: B12
Name: B Block Room 12
Campus: Main Campus
```

Booking users select from a searchable dropdown.

Allow administrators to add, disable and reorder rooms.

Do not delete locations that are referenced by historical bookings.

---

# 21. Exam / Booking Types

Create configurable booking types.

Examples:

```text
PDF
Microsoft Word
Locked Down
Safe Exam Browser
Online Assessment
Web Browser
Other
```

Suggested fields:

```text
name
description
enabled
instructions_for_user
instructions_for_it
requires_approval
display_order
```

Example:

```text
Safe Exam Browser

Instructions for IT:
Ensure SEB is installed and validate the required configuration before deployment.
```

An administrator manages these under:

**Administration → Booking Types**

Do not hard-code the examples above.

---

# 22. Student Information

Support:

```text
Student Name
```

as an optional field initially.

Design the schema so additional student identifiers can later be added if necessary.

Student data must be associated with the booking, not the underlying resource.

An exam may potentially involve multiple students.

Therefore consider supporting either:

### Simple initial approach

One optional student name per booking.

or preferably:

### Structured approach

```text
booking_students

id
booking_id
student_name
student_identifier optional
```

Allow one or several students to be attached to a booking.

Do not require student integration with another school system in the initial version.

---

# 23. Two Booking Modes

Support both:

## A. Select Specific Assets

The user selects:

```text
Laptop 03
Laptop 07
Laptop 14
```

## B. Request Quantity

The user requests:

```text
6 Exam Laptops
```

The system can automatically choose six available resources.

This is important.

In many cases the user does not care which physical laptops are supplied.

---

# 24. Allocation vs Reservation

Separate:

```text
Quantity Requested
```

from:

```text
Physical Assets Allocated
```

For example:

```text
Booking EX-2026-00421

Requested:
6 Exam Laptops

Allocated:
10023
10024
10027
10032
10041
10043
```

This lets IT substitute a failed laptop without modifying the purpose/time of the booking.

Example:

```text
Replace Asset 10027

With:
Asset 10044

Reason:
Battery failure
```

Record this action in the audit log.

---

# 25. Additional Resources

Bookings may contain multiple types of resource.

Example:

```text
6 × Exam Laptops
6 × Chargers
2 × Wireless Mice
```

Implement a booking and booking-items model rather than storing one resource directly against the booking.

Conceptually:

```text
bookings

booking_items

booking_resource_allocations
```

This allows future expansion.

---

# 26. Availability and Conflict Detection

Availability must be calculated using time ranges.

Two bookings overlap when:

```text
requested_start < existing_end
AND
requested_end > existing_start
```

Use robust database transactions/locking to prevent concurrent users from creating conflicting allocations.

Do not rely only on front-end availability checks.

Immediately before committing a booking:

1. Begin transaction.
2. Recheck availability.
3. Lock/select affected allocations appropriately.
4. Commit only if resources remain available.
5. Otherwise tell the user availability changed and refresh the grid.

---

# 27. Preparation and Return Buffers

Resource pools should support:

```text
Preparation Buffer
Return Buffer
```

Example:

```text
Booking:
10:00–11:00

Preparation buffer:
15 minutes

Return buffer:
15 minutes

Effective unavailable range:
09:45–11:15
```

The buffer should be configurable per resource pool.

This allows IT time to:

* prepare equipment
* deliver it
* collect it
* reset/reconfigure it

---

# 28. Booking Statuses

Implement:

```text
Draft
Pending Approval
Approved
Rejected
Cancelled
Completed
```

Potentially:

```text
Awaiting Allocation
Partially Allocated
```

where quantity bookings require IT allocation.

Avoid using one status field to represent both booking approval and physical allocation if they are conceptually different.

Consider fields such as:

```text
approval_status
allocation_status
lifecycle_status
```

if that makes the model cleaner.

---

# 29. Default Approval Logic

Initial requirement:

A normal Exam Laptop booking should automatically approve if:

* there are no resource conflicts
* the booking is at least **6 hours away**
* no selected booking type requires manual approval
* no configured approval rule requires intervention

The six-hour value must be configurable.

Example:

```text
Minimum Automatic Approval Lead Time:
6 hours
```

---

# 30. Configurable Approval Conditions

Design this so additional rules can be configured.

Useful conditions include:

```text
Require approval if less than X hours notice

Require approval on weekends

Require approval outside normal hours

Require approval for more than X resources

Require approval for a specific resource pool

Require approval for a specific exam type
```

Do not build a huge workflow engine.

Implement sensible, maintainable configurable rules.

---

# 31. Booking Submission Workflow

Flow:

```text
Authenticated user
      ↓
Select Resource Pool
      ↓
Select Date
      ↓
Select Start / Finish
      ↓
Select Room
      ↓
Select Booking Type
      ↓
Student information
      ↓
Quantity / Specific Assets
      ↓
Additional Resources
      ↓
Review
      ↓
Final availability validation
      ↓
Create Booking
      ↓
Automatic approval OR Pending Approval
      ↓
Notifications
```

---

# 32. Booking Reference

Every booking should have a human-friendly reference.

Example:

```text
EX-2026-00421
```

Do not expose sequential database IDs as the only public identifier.

Make the prefix configurable by resource/application if practical.

---

# 33. User Confirmation Email

After submission, email the booking user.

Example information:

```text
Exam Laptop Booking

Reference:
EX-2026-00421

Thursday 20 August 2026
10:17 AM – 11:43 AM

Room:
B12

Exam Type:
Safe Exam Browser

Resources:
6 Exam Laptops

Status:
Approved
```

or:

```text
Status:
Awaiting IT Approval
```

Include a secure link:

```text
View Booking
```

---

# 34. IT Notification

Notify a configurable IT address.

Initial intended address:

```text
itservice@example.edu.au
```

Do not hard-code this value.

Configuration example:

```env
IT_NOTIFICATION_ADDRESS=itservice@example.edu.au
```

Provide web settings where non-secret notification addresses can be changed.

IT notification should contain:

```text
Booking Reference
Requester
Student(s)
Date
Time
Room
Exam Type
Requested Resources
Allocated Resources
Notes
Approval Status
```

---

# 35. Helpdesk Reply-To

Emails should support a configurable Reply-To.

For example:

```text
helpdesk@example.edu.au
```

Again, do not hard-code this.

Provide configuration such as:

```text
Helpdesk / Reply-To Address
```

---

# 36. Approve / Reject Email Actions

For bookings requiring approval, the IT email should include:

```text
[ Approve ]
[ Reject ]
[ View Booking ]
```

These should use securely signed, expiring links.

Do not expose ordinary authentication tokens.

Laravel signed URLs would be suitable.

For sensitive/destructive actions, design carefully around GET versus POST.

A good workflow would be:

### Approve

Magic link opens a concise booking confirmation page.

If the signed link is valid, IT can approve with one click.

### Reject

Signed link opens:

```text
Reject Booking EX-2026-00421

Reason:
[________________________________]
[________________________________]

[ Reject Booking ]
```

A rejection reason is required.

---

# 37. Rejection Notification

When rejected, notify the booking user.

Include:

```text
Booking EX-2026-00421 has been declined.

Reason:
Six exam laptops cannot be prepared for this timeslot.

Please contact IT if you need assistance finding another time.
```

Set Reply-To to the configured helpdesk email.

Store:

```text
rejected_by
rejected_at
rejection_reason
```

---

# 38. Calendar Integration

Generate an iCalendar `.ics` event for relevant notifications.

An approved booking may generate:

```text
Exam Laptops – B12 – 6 Devices
```

Calendar description:

```text
Resource Booking

Reference: EX-2026-00421

Room: B12
Exam Type: Safe Exam Browser

Requested:
6 Exam Laptops

Allocated:
10023
10024
10027
10032
10041
10043

Booked by:
Jane Smith
```

Initially email/calendar notifications can go to:

* booking owner
* IT notification mailbox

Do not automatically send student calendar invitations.

---

# 39. My Bookings

Provide:

**My Bookings**

Tabs/filters:

```text
Upcoming
Pending
Previous
Cancelled
Rejected
```

Cards/list should show:

```text
20 Aug 2026
10:15–11:45
C05
6 Exam Laptops
Safe Exam Browser
APPROVED

[ View ]
[ Modify ]
[ Cancel ]
```

---

# 40. All Bookings View

Authenticated users may have a calendar/list view showing all bookings.

Because the application is restricted to staff, booking details including student information may be displayed to authorised staff.

Still implement proper role-based access rather than simply assuming every endpoint is public after login.

Provide:

```text
Day
Week
Month
List
```

views where practical.

---

# 41. IT Operations Dashboard

Create an IT-oriented dashboard.

Example:

```text
TODAY

08:30 – 09:30
B12
4 Exam Laptops
PDF
Allocated ✓

10:15 – 11:45
C05
8 Exam Laptops
Safe Exam Browser
Allocated ✓

13:25 – 14:10
B07
3 Exam Laptops
Microsoft Word
Awaiting Approval ⚠
```

Dashboard metrics:

```text
Bookings Today
Devices Required Today
Bookings Tomorrow
Pending Approvals
Awaiting Allocation
Unavailable Assets
```

---

# 42. IT Logistics / Preparation View

This is an important feature.

Provide a concise operational view like:

```text
Thursday 20 August 2026

TIME       ROOM     QTY     TYPE                 ASSETS

08:30      B12      4       PDF                  01 03 07 11

10:15      C05      8       Safe Exam Browser    02 04 05 06...

13:25      B07      3       Word                 TBA
```

Allow filtering by:

* day
* room
* resource pool
* booking type
* approval status
* allocation status

Provide a printer-friendly view.

Potentially allow export as:

* PDF later
* CSV
* print

PDF is not mandatory for the initial implementation if browser print is good.

---

# 43. Administration — Resources

Provide:

**Administration → Resource Pools**

Example:

```text
Exam Laptops       20
Loan Laptops       10
Chargers            30
```

Selecting a pool shows its resources.

Functions:

```text
Add Manual Resource

Import From Snipe-IT

Synchronise Snipe-IT Assets

Disable Resource

Move Resource Between Pools
```

---

# 44. Administrative Booking Override

Administrators must be able to override bookings.

Examples:

* approve a booking
* reject a booking
* modify time
* change room
* alter requested quantity
* swap physical laptops
* allocate an otherwise unavailable asset
* move a resource
* create a booking on behalf of another user

Conflict overrides must:

* show a prominent warning
* require an explicit confirmation
* optionally require an override reason
* create an audit event

Never silently allow overlapping allocations.

---

# 45. Creating Bookings for Other Users

IT/admin users need:

```text
Booking Owner
[ Search user ▼ ]
```

They can select another application user.

The booking remains associated with that user.

If appropriate, send notifications to:

* booking owner
* person creating the booking

Store:

```text
created_by_user_id
booking_owner_user_id
```

separately.

---

# 46. Manual User Creation

Provide:

**Administration → Users → Add User**

Fields:

```text
Name
Email
Role
Enabled
```

OIDC subject may initially be null.

At first successful authentication, associate the OIDC identity safely.

---

# 47. Application Settings

Provide an administration settings UI.

## General

```text
Site Name
School/Organisation Name
Site Logo
Timezone
Date Format
Application Title
```

Default timezone should be configurable and should support:

```text
Australia/Brisbane
```

## Booking

```text
Default Booking Duration
Minimum Lead Time
Maximum Future Booking Period
School Day Start
School Day Finish
Allow Weekends
Preparation Buffer
Return Buffer
```

Some values may be overridden at the resource-pool level.

## Notifications

```text
Sender Name
Sender Address
Reply-To Address
IT Notification Address
```

## Appearance

```text
Application Name
Logo
```

Do NOT store or expose passwords, OIDC secrets, database credentials or Snipe-IT API tokens as ordinary editable database settings unless an appropriately secure secret-storage design is specifically implemented.

---

# 48. Audit Logging

Implement comprehensive auditing.

Capture events such as:

```text
21:13 Jane Smith created EX-2026-00421

21:13 System auto-approved EX-2026-00421

07:53 Nick replaced Asset 10027 with Asset 10044

07:54 Nick changed quantity from 4 to 5

08:01 Jane cancelled EX-2026-00421

08:10 Nick rejected EX-2026-00429
Reason: insufficient preparation time
```

Audit events should include where relevant:

```text
event_type
actor_user_id
booking_id
resource_id
timestamp
IP address
old_values
new_values
description
```

Do not store authentication secrets in audit logs.

---

# 49. Security

Treat this as a production school application.

Implement:

* CSRF protection
* output escaping
* input validation
* server-side authorisation
* secure sessions
* secure cookies
* appropriate SameSite settings
* HTTPS assumptions in production
* rate limiting where appropriate
* signed approval/rejection links
* least-privilege integration credentials
* protection against mass-assignment vulnerabilities
* safe file/logo upload handling
* secure secret management
* database transaction safety
* protection against IDOR
* security headers

Never rely on UI controls alone for authorisation.

---

# 50. Privacy

The system contains staff and potentially student information.

Do not expose booking data through unauthenticated APIs.

Only authenticated authorised users should see booking information.

Ensure application logs do not unnecessarily contain student data.

Keep secrets and authentication tokens out of logs.

---

# 51. Embedding

Do NOT require the authenticated application to function inside an iframe.

OIDC, cookies and browser security controls make this undesirable.

The preferred deployment is a standalone URL such as:

```text
https://examlaptops.example.edu.au
```

A separate optional embeddable widget may later expose non-sensitive information such as:

```text
Exam Laptop Availability

Today:
12 available

[ Book Equipment ]
```

but the actual booking application should open normally.

---

# 52. Database Design

Design normalized migrations/models for at least:

```text
users

roles/permissions if necessary

resource_pools

resources

resource_quantities where appropriate

external_asset_links

locations

booking_types

bookings

booking_students

booking_items

booking_resource_allocations

booking_approvals / approval metadata

audit_events

settings

integration_sync_logs
```

Do not blindly use this exact schema if a cleaner relational design exists, but preserve these domain concepts.

---

# 53. Soft Deletes / Historical Data

Historical booking information must remain reliable.

Do not hard-delete entities that have historical references.

Use:

* enabled/disabled state
* archived state
* soft deletes

where appropriate.

For example, deleting room B12 must not destroy the room associated with a booking from the previous year.

---

# 54. Date/Time Storage

Store timestamps in a consistent format, preferably UTC internally.

Render using the configured application timezone.

Initial deployment timezone:

```text
Australia/Brisbane
```

Correctly handle timezone conversions for OIDC/API/calendar operations.

---

# 55. Accessibility

The visual laptop grid must not depend exclusively on colour.

Use:

* icon
* text
* status
* disabled state
* appropriate ARIA semantics

Ensure the application remains keyboard accessible.

---

# 56. Responsive UI

Desktop is the primary operational interface, but pages should work properly on:

* laptop
* tablet
* phone

The resource grid should dynamically adapt.

For approximately 20 laptops, a layout such as:

```text
5 × 4
```

may make more sense than forcing a 6 × 6 layout.

Let CSS adapt based on viewport and number of resources.

---

# 57. Search

Provide practical search capabilities.

For bookings:

```text
Booking Reference
User
Student
Room
Asset Tag
Date
Exam Type
```

For resources:

```text
Asset Tag
Name
Serial
Model
Pool
Status
```

---

# 58. Notifications Architecture

Create a notification service abstraction rather than placing direct email code throughout controllers.

Initial:

```text
SMTP email
ICS
```

Future integrations should remain possible, for example:

```text
Microsoft Graph
Teams
Webhooks
```

Do not implement these future channels unless required.

---

# 59. Background Jobs

Use queued jobs where appropriate for:

```text
Email sending
Calendar notifications
Snipe-IT synchronization
Potential reminder notifications
```

A failed email must not roll back an otherwise valid booking.

Log notification failures and permit retries.

---

# 60. Useful Reminder Feature

Implement or design for configurable reminders.

Examples:

```text
Reminder to user:
24 hours before booking

IT preparation reminder:
Beginning of day
```

The initial release may include a basic reminder mechanism if straightforward.

---

# 61. Upcoming Booking Warnings

On the IT dashboard flag:

```text
Booking tomorrow with assets not allocated

Booking contains unavailable Snipe-IT asset

Booking <6 hours away still pending

Resource marked Maintenance but allocated tomorrow
```

These operational warnings are high-value.

---

# 62. Home Dashboard for Staff

After login, show something simple:

```text
Resource Booking

[ + New Booking ]

Upcoming Bookings

Thursday
10:15
B12
6 Exam Laptops
Approved

Friday
13:30
C03
2 Exam Laptops
Pending
```

Do not drop ordinary teachers into a complex administrative calendar.

---

# 63. Snipe-IT Asset Links

For linked assets, administrators should be able to click:

```text
View in Snipe-IT
```

Construct this safely from the configured Snipe-IT base URL/external asset identifier.

Display useful synchronized information such as:

```text
Asset Tag
Serial
Model
Snipe-IT Status
Snipe-IT Location
Last Synchronized
```

---

# 64. Snipe-IT Import Duplication Protection

Do not allow the same Snipe-IT asset to accidentally exist as multiple linked booking resources.

Create an appropriate unique constraint around:

```text
external_source
external_id
```

If the asset is already linked, show:

```text
Already assigned to:
Exam Laptops
```

Allow an administrator to move it rather than duplicate it.

---

# 65. Snipe-IT Sync Safety

External synchronization must NEVER overwrite application-specific information such as:

```text
resource pool
booking availability override
local notes
future reservations
booking history
```

Synchronize only fields owned by Snipe-IT.

Clearly distinguish external fields from local fields.

---

# 66. API

Create a sensible internal REST API structure even if the initial UI uses Laravel server-rendered pages/Livewire.

Examples:

```text
GET /api/resource-pools
GET /api/resource-pools/{id}/availability
GET /api/bookings
POST /api/bookings
GET /api/bookings/{reference}
```

Protect API endpoints appropriately.

Do not expose an unauthenticated general-purpose API.

The UI does not need to be implemented as an SPA to use sensible application service boundaries.

---

# 67. Health Endpoint

Provide a health endpoint suitable for container monitoring.

For example:

```text
/health
```

Return basic application/database health without exposing secrets.

Potential checks:

```text
Application: Healthy
Database: Healthy
```

Do not make external Snipe-IT availability cause the whole application health check to fail unless specifically configured.

Instead report external integration degradation separately.

---

# 68. Logging

Use structured application logging.

Capture:

* application errors
* failed integrations
* failed emails
* sync failures
* authentication failures where appropriate

Do not log:

* OIDC client secrets
* access tokens
* Snipe-IT tokens
* database passwords

---

# 69. Backups

Document how to back up:

* database
* uploaded logo/files
* relevant persistent volumes

Application containers should be disposable.

---

# 70. Testing

Build automated tests.

At minimum cover:

## Authentication

* unauthenticated users cannot access booking data
* normal users cannot access administration
* admin role works

## Booking conflicts

* same asset cannot be booked concurrently
* boundary times work
* preparation buffer works
* return buffer works
* cancelled bookings release resources

## Quantity bookings

* available quantity is calculated correctly
* allocation does not exceed availability

## Approval

* ≥ configured lead time can auto-approve
* below lead time goes pending
* manual approval works
* rejection requires a reason

## Snipe-IT

* import works with mocked API
* failed API does not crash application
* sync updates external fields
* sync does not overwrite local booking state
* duplicate imports are prevented

## Security

* users cannot modify other bookings without permission
* ID manipulation does not expose unauthorized records
* expired signed approval links fail

---

# 71. Seed / Demonstration Data

Provide optional development seed data.

For example:

```text
20 Exam Laptops

Exam Laptop 01
...
Exam Laptop 20

Rooms:
B12
B14
C05
C07
LIB-1

Booking Types:
PDF
Microsoft Word
Safe Exam Browser
Locked Down
Online Assessment
```

These are DEVELOPMENT/DEMO values only.

Do not automatically seed production with fake student/user data.

---

# 72. UX Expectations

Do not treat design as an afterthought.

The application should feel like a modern internal school service rather than a CRUD database frontend.

Prioritize:

* large obvious booking controls
* visual availability
* minimal number of clicks
* clear status badges
* readable calendars
* good empty states
* clear success messages
* useful validation messages
* confirmation before destructive actions

Example user journey should be achievable in approximately:

```text
New Booking
→ Date/Time
→ Room/Exam Type
→ Select 6 laptops
→ Book
```

---

# 73. Suggested Implementation Phases

Implement iteratively.

## Phase 1 — Foundation

Build:

* Laravel project
* Docker
* database
* migrations
* authentication architecture
* OIDC
* users
* roles
* application layout

Ensure tests and Docker deployment work before proceeding.

## Phase 2 — Resource Management

Build:

* resource pools
* resources
* manual resources
* locations
* booking types
* admin UI

## Phase 3 — Snipe-IT

Build:

* API client
* connection test
* asset search
* import selection
* local external asset records
* sync
* error handling
* sync logs

Do not make the rest of the application directly dependent upon API availability.

## Phase 4 — Booking Engine

Build:

* bookings
* booking items
* time ranges
* conflict detection
* quantity booking
* individual allocation
* buffers
* booking ownership
* student details

Write comprehensive booking conflict tests.

## Phase 5 — Visual Booking Experience

Build:

* date/time selector
* room selector
* booking type selector
* laptop/resource grid
* multi-selection
* quantity booking
* live availability
* responsive interface

## Phase 6 — Approval Workflow

Build:

* auto-approval
* approval rules
* pending bookings
* admin approve/reject
* rejection reasons
* signed links

## Phase 7 — Notifications

Build:

* user confirmations
* IT notifications
* Reply-To
* ICS/calendar invitations
* queueing/retries

## Phase 8 — Operational Views

Build:

* My Bookings
* all bookings
* admin dashboard
* IT preparation/logistics view
* filters
* alerts
* printer-friendly view

## Phase 9 — Hardening

Complete:

* audit logs
* security review
* race-condition testing
* integration failure testing
* responsive testing
* accessibility checks
* documentation
* backups
* health checks

---

# 74. Implementation Behaviour for the Coding Agent

Before implementing each major component:

1. Inspect the existing repository.
2. Do not replace functioning architecture unnecessarily.
3. Follow established project conventions.
4. Implement migrations before depending upon database fields.
5. Keep controllers thin.
6. Put business logic into appropriate services/actions.
7. Use policies/gates for authorization.
8. Use form request validation.
9. Add tests with functionality.
10. Run tests after meaningful changes.
11. Fix failures before moving on.
12. Keep `.env.example` current.
13. Update the README as deployment requirements change.
14. Never commit credentials or tokens.

Do not create fake implementations simply to make the UI appear complete.

External services such as Snipe-IT and OIDC should use proper interfaces/service classes so they can be mocked in tests.

---

# 75. Definition of Success

The first production release should allow the following real-world scenario:

A teacher signs in using the school's OpenID/Entra identity.

They select:

```text
Exam Laptops
```

They choose:

```text
Thursday 20 August 2026

10:17–11:43

Room B12

Safe Exam Browser

Student:
Optional

Number Required:
6
```

The interface immediately displays which laptops are available.

The teacher can either:

```text
Select six specific laptops
```

or:

```text
Request six and allow the system/IT to allocate them.
```

They submit the request.

The application performs an authoritative server-side availability check.

If there is more than the configured six-hour notice period and no other approval rule applies, the booking is automatically approved.

The teacher receives confirmation.

IT receives the booking details and calendar information.

The booking appears on:

* the teacher's My Bookings page
* the organisation booking calendar
* the IT dashboard
* the IT daily logistics view

The physical laptops allocated to the booking were originally selected from Snipe-IT and retain links to their:

* Snipe-IT asset ID
* asset tag
* serial number
* model
* current external status

If one laptop fails before the exam, IT opens the booking and substitutes another available Exam Laptop sourced from Snipe-IT.

The teacher's booking remains intact.

The resource allocation is changed.

The action is audited.

If Snipe-IT happens to be unavailable at that moment, the booking system continues functioning using the previously synchronized asset information.

Manual resources such as chargers can also be attached to the booking even though those items do not exist in Snipe-IT.

This combination of:

**resource pools + Snipe-IT-linked assets + manual resources + flexible booking + physical allocation**

should be treated as the central architecture of the application.
