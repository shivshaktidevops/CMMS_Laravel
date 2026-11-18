# Phase 2 Implementation

## Equipment Master additions

`equipment` now supports:

- `asset_uid` — unique asset identity
- `machine_tag` — unique operational/QR-ready tag value
- `location_id` — normalized location
- `parent_equipment_id` — machine/sub-machine hierarchy
- `serial_number`
- `manufacturer`
- `model_number`
- `commissioned_at`
- `critical_downtime_minutes`

Legacy text fields such as `plant`, `department` and `location` remain in place for API/UI compatibility.

## Location hierarchy

New `locations` table supports a self-referencing hierarchy:

`Plant -> Workshop -> Area -> Line -> Machine Area`

API:

- `GET /api/locations`
- `GET /api/locations/tree`
- `POST /api/locations` (admin)
- `PUT /api/locations/{id}` (admin)
- `DELETE /api/locations/{id}` (admin; deactivates)

Hierarchy cycles and cross-plant parent mappings are rejected.

## Equipment components

New `equipment_components` table supports machine component hierarchy:

`Equipment -> Gearbox -> Bearing -> Sub-component`

API:

- `GET /api/equipment/{equipmentId}/components`
- `GET /api/equipment/{equipmentId}/components/tree`
- `POST /api/equipment/{equipmentId}/components` (admin)
- `PUT /api/equipment/{equipmentId}/components/{id}` (admin)
- `DELETE /api/equipment/{equipmentId}/components/{id}` (admin; deactivates)

Component codes are unique per equipment. Cross-equipment parent references and hierarchy cycles are rejected.

## Machine tag lookup

`GET /api/equipment/tag/{tag}` searches in this order-compatible set:

- machine tag
- asset UID
- existing equipment code

This is ready to become the destination for QR/barcode scanning in a later UI/mobile integration without changing the Equipment Master data model again.

## Admin UI integration

The existing Admin page was retained and extended with:

- Asset UID and Machine Tag
- Structured Location dropdown
- Parent Machine dropdown
- Serial / manufacturer / model
- Commissioning date
- Critical downtime limit
- Locations master panel
- Components action and component hierarchy modal

No global redesign or theme change was introduced.
