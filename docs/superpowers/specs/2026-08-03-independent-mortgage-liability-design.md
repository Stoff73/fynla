# Independent Mortgage Liability for Shared Property

## Purpose

Fynla must model property ownership and mortgage liability independently. A user may own any share of a property while being solely liable for the whole mortgage. In that case, Fynla must show the user's property share separately from their full mortgage liability.

Example: a user owns 30% of a property with a friend who owns 70%; the mortgage is solely in the user's name. The user's net position is 30% of the full property value less 100% of the mortgage balance. The same rule applies if the user owns 70% (or any other share): title share does not determine mortgage liability.

## Current Defect

The property form copies a shared property's ownership type, owner and percentage into its mortgage form. It converts tenants-in-common property ownership to a joint mortgage and later keeps the mortgage percentage synchronised with the property percentage. Some property views also derive mortgage values and labels from property ownership instead of from the mortgage record.

This understates a sole borrower's liability and therefore overstates equity, net worth, affordability and estate value.

## Behaviour

### Separate Concepts

Property ownership records title ownership:

- Individual, joint tenancy, tenants in common, or trust.
- For tenants in common, the stored percentage is the user's title share.

Mortgage records legal liability:

- `individual`: the borrower is responsible for 100% of the balance and payment.
- `joint`: responsibility is split according to the mortgage record's percentage and joint borrower; it does not inherit title percentages.

`tenants_in_common` remains a property-only concept. It is not a mortgage ownership type.

### Form Flow

When adding or editing a property with a mortgage, the form presents mortgage liability separately from property ownership:

- Individual mortgage (the default): persists `ownership_type = individual`, `ownership_percentage = 100`, and no mortgage joint owner.
- Joint mortgage: permits the user to set its borrower and liability split independently of the property ownership split.

Changing the property's ownership type or title percentage must not mutate mortgage liability fields. Existing saved mortgage data remains authoritative when editing.

### Calculations and Presentation

All mortgage displays and calculations must read the mortgage's own ownership fields:

- An individual mortgage belongs wholly to its recorded user.
- A joint mortgage uses its own stored split for balance and monthly payment.
- Property value, rental income and shared non-mortgage property costs continue to use the property share.
- Personal equity is the user's property share minus the user's mortgage share.

The existing backend `CalculatesOwnershipShare::calculateUserMortgageShare()` is the canonical source for server-side balance calculations. Client-side property views must apply the same mortgage-specific rule rather than property ownership percentage.

## Downstream Scope

The corrected mortgage record must flow consistently into:

- net worth and property equity;
- property detail/card and property financials views;
- monthly commitments and cash-flow;
- household planning;
- estate planning, inheritance-tax calculations and estate reports;
- dashboard and mobile mortgage summaries.

The server-side aggregators already call the mortgage-share trait. The implementation must preserve that path and correct the form and client views that currently overwrite or recompute the record from the property share.

## Existing Records

No automatic data migration will infer whether an existing shared-property mortgage is sole or joint: property title does not prove legal mortgage liability. Affected records must be explicitly edited to the correct mortgage liability after this feature is deployed.

## Regression Coverage

Tests will cover a 30/70 tenants-in-common property owned with an external co-owner and a sole mortgage:

- Property share is 30% of full value.
- Mortgage balance and monthly payment are 100% attributable to the user.
- Equity, net-worth and estate liability use those figures.
- Saving or editing the property does not change the individual mortgage into a joint mortgage.

An existing joint-mortgage scenario will confirm that its mortgage-specific split continues to work independently of property title percentages.

## Out of Scope

- Changing the legal meaning of property ownership.
- Inferring or bulk-correcting historical mortgage borrowers.
- Deployment to the development or live server.
