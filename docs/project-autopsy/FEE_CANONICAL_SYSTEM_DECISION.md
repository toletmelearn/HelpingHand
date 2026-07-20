# Fee Canonical System Decision

Date: 2026-06-03

## Canonical Fee System

The canonical fee system for HelpingHand is:

- `FeeStructure`
- `FeeStructureItem`
- `FeeType`
- `StudentFeeAssignment`
- `FeeCollection`
- `FeeCollectionItem`

New fee work should build on this system only.

## Legacy / Deprecated

The following are legacy/deprecated for new money writes:

- `Fee` model
- `Admin\FeeController`

Do not build new fee features on the legacy `Fee` model.

## Quarantine / Experimental

The following areas must be treated as quarantined or experimental until routes and schema contracts are repaired:

- `InstallmentFeeController` write flows
- `ProfessionalFeeManagementController` write flows
- `ProfessionalFeeManagementService` write methods

These flows overlap with the canonical fee system but currently depend on unsafe or inconsistent schema/model contracts.

## Payment / Stripe

Payment gateway and Stripe work must remain separate for a later dedicated payment phase.

Do not mix Stripe/webhook changes into canonical fee-domain cleanup.

## Rule

No new fee feature should be built on the legacy `Fee` model.

