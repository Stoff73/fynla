# Guide to Connecting to the Addepar API

This detailed guide covers how to connect to the Addepar API, based on official documentation. It includes an overview, setup steps, authentication, making requests, key concepts, and troubleshooting. Addepar is a wealth management platform that aggregates portfolio data, enables analysis, and supports reporting for complex investment scenarios. The API allows integration with external systems, automation of workflows, and custom solutions.

## Prerequisites
- **Active Subscription**: You need an active Addepar subscription to access the API. Contact Addepar sales if you're not a client.
- **User Permissions**: Ensure your Addepar account has the necessary permissions. If you're a developer, contact your firm administrator for API access.
- **Development Environment**: For testing, request a development environment from your Addepar contact.
- **Tools**: Use HTTP clients like Postman for testing or cURL for command-line requests. For production, use libraries like Requests in Python or jQuery.Ajax in JavaScript.

## Step 1: Confirm API Access
Follow these steps to verify and set up access:
1. Sign in to your Addepar account.
2. Navigate to the Global Navigation Bar and click **Firm Administration**.
3. Under **Admin Tools**, click **API Access Key**.
4. If available, proceed with setup. If not, request access from your firm administrator.

## Step 2: Assign API Permissions
To manage API integrations:
1. In **Firm Administration**, click **Users** under **User Permissions** in the left menu.
2. Select the user (e.g., yourself or an API-specific user).
3. Go to the **Permissions** tab.
4. Scroll to **API Access** and select **Create, edit, and delete**.

API permissions grant access to data and tools based on the user's profile. Create separate profiles for third-party integrations to limit scope.

## Step 3: Generate API Key and Secret
API requests require a key-secret pair for authentication:
1. In **Firm Administration**, select **Firm Settings**, then click **API Access Key** in the left menu.
2. Click the **+** button in the table header.
3. Enter a description (e.g., the integration name).
4. Click **Submit**.
5. Copy the generated **Key** and **Secret** immediately—store them securely (e.g., in a password manager). They won't be shown again.

- Each key respects the creator's permissions.
- Create unique keys per integration for better tracking and security.
- Review usage via "Display all access keys" to see the "last used on" date.

**Security Note**: Never share keys in public repos, emails, or client-side code. Use HTTPS for all requests.

## Step 4: Authentication
Addepar uses HTTP Basic Auth (or OAuth for bearer tokens—see advanced docs for OAuth setup).

### Constructing the Authorization Header
1. Combine your **API Key** and **Secret** with a colon (e.g., `key:secret`).
2. Base64-encode the string.
3. Prepend `Basic ` (e.g., `Basic <encoded-string>`).

### Required Headers
Include these in every request:
- **Authorization**: `Basic <Base64-encoded Key:Secret>`
- **Addepar-Firm**: Your firm ID (find it by generating an API URL in the Analysis Tool: Export > Generate API URL; it's the `addepar_firm=` value).
- For POST/PATCH: **Content-Type**: `application/vnd.api+json`

The base URL is `https://<your-firm-domain>.addepar.com/api/v1` (e.g., `https://examplefirm.addepar.com/api/v1`).

### Example: GET Request (cURL)
```bash
curl --request GET \
  --url 'https://examplefirm.addepar.com/api/v1/entities/1234' \
  --header 'Addepar-Firm: 1' \
  --header 'Authorization: Basic YmZiYWFlZjUtZmYwMC00MWZkLWE0Y2YtYjg5MjcxNmQzZGVjOjlPdU40d3RRbzZ1MkEwSXZnb3U4Y3FOWVZjZmsyV2g0OHkzTFZBZmY='
```


### Example: POST Request (cURL)
```bash
curl --request POST \
  --url 'https://examplefirm.addepar.com/api/v1/entities' \
  --header 'Addepar-Firm: 1' \
  --header 'Content-Type: application/vnd.api+json' \
  --header 'Authorization: Basic YmZiYWFlZjUtZmYwMC00MWZkLWE0Y2YtYjg5MjcxNmQzZGVjOjlPdU40d3RRbzZ1MkEwSXZnb3U4Y3FOWVZjZmsyV2g0OHkzTFZBZmY=' \
  --data-raw '{
    "data": {
      "type": "entities",
      "attributes": {
        "original_name": "New entity",
        "currency_factor": "USD",
        "model_type": "PERSON_NODE"
      }
    }
  }'
```


## Step 5: Making Your First Request
Start with a simple endpoint like retrieving entities or portfolio views.

### Example: Get Portfolio Views
Endpoint: `GET /v1/portfolio/views`

This lists saved analysis views. Use the authentication setup above.

cURL Example (adapt with your details):
```bash
curl --request GET \
  --url 'https://examplefirm.addepar.com/api/v1/portfolio/views' \
  --header 'Addepar-Firm: 1' \
  --header 'Authorization: Basic <your-encoded-cred>'
```

Produces JSON, CSV, TSV, or XLSX.

For dynamic queries without a view ID, use `POST /v1/portfolio/query`.

## Key Concepts in Addepar
Understanding the data model is crucial for effective API use:

- **Ownership Structure**: A hierarchy of households, clients, legal entities, accounts, and investments. Households can own everything; investments own nothing.
- **Entities**: Building blocks like households (top-level), clients, legal entities, accounts (portfolios), and investments.
- **Positions**: Connections between entities, with attributes like value, ownership percent, and dates (e.g., linking an account to a stock).
- **Attributes**: Qualitative/quantitative details for organization (e.g., asset class, region) applied to portfolios, positions, and transactions.
- **Analysis & Transactions**: Aggregate data in views; transactions have attributes like trade date.

The Portfolio API extracts data based on views or queries. Other APIs handle positions, entities, etc.

## Best Practices
- Use HTTPS only; HTTP requests fail.
- Monitor rate limits (default unknown; request increases via Addepar contact).
- Handle timeouts (60 seconds) and pagination for large responses (e.g., entities, positions).
- Secure keys and appoint an admin to monitor usage.

## Troubleshooting
- **Inconsistent Results**: Check permissions.
- **400 Errors (OAuth)**: Ensure redirect URIs are whitelisted.
- **Timeouts**: Requests exceed 60 seconds.
- **Support**: Clients use in-app tool or call (855) 464-6268, option 2. Partners email partners@addepar.com; general inquiries to info@addepar.com.

For more, explore the [Addepar Developer Portal](https://developers.addepar.com/) interactive explorer or specific API docs (e.g., Positions, Entities). If integrating with tools like Salesforce, refer to partner-specific guides.

## Overview of Data Available via Addepar API

Addepar's API provides access to a range of financial and client-related data for wealth management, organized across several key APIs such as Entities, Attributes, Positions, Portfolio, and Transactions. The platform models data hierarchically, with entities representing clients, households, accounts, and investments. Standard fields are limited, but custom attributes allow firms to extend data with specifics like risk profiles or ages. Below is a breakdown by API category, focusing on client-related information (e.g., names, demographics, financial details).

### Entities API
This API manages core building blocks like clients (modeled as "PERSON_NODE"), households, trusts, accounts, and investments. It supports CRUD operations (create, read, update, delete).

- **Client Name**: Retrieved via the `original_name` field (e.g., "Adam Smith"). This is the primary identifier for clients and other entities.
- **Risk Profile and Age**: Not standard fields; these are typically stored as custom attributes (e.g., `_custom_risk_profile_123` or `_custom_age_456`). Firms define these to capture demographic or preference data.
- **Other Client/Entity Details**:
  - `model_type`: Specifies type (e.g., "PERSON_NODE" for individuals).
  - `display_name`: Alternate name for display purposes.
  - `created_at` and `modified_at`: Timestamps for entity creation and last update.
  - `ownership_type`: For non-clients (e.g., "PERCENT_BASED" for accounts).
  - `currency_factor`: Currency code (e.g., "USD").
  - Custom or external IDs: Links to external systems (e.g., Salesforce IDs via `external_id_salesforce`).
- **Filtering Options**: Query by IDs, types, dates, or linking status (e.g., linked accounts).

Entities can own other entities (e.g., a client owns an account), forming a ownership graph.

### Attributes API
Attributes add qualitative/quantitative details to entities, positions, or transactions. This API discovers available attributes, which can be standard (predefined) or custom (firm-specific).

- **Client-Related Attributes**:
  - Standard: Basic like `account_number`, `client` (references the client entity), `account_name`, or performance metrics (e.g., time-weighted return "TWR").
  - Custom: Flexible for risk profile (e.g., as a "Word" or "Number" type), age (e.g., as a "Number"), or other demographics (e.g., `_custom_gic_taxonomy_637951` for custom taxonomies). Up to 10 attributes per entity type in integrations like Salesforce.
- **Attribute Properties**:
  - `output_type`: E.g., "Percent", "Number", "Word", "Boolean".
  - `category`: Groups like "Performance Metrics", "Holding Details", "Account Details".
  - `display_name`: Human-readable name (e.g., "Asset Class").
  - Usage: Applied to columns, groupings, filters, or directly to entities/positions.
- Custom attributes are prefixed with "_custom_" in API responses for easy identification.

### Positions API
Positions represent ownership connections between entities (e.g., a client owning shares in a stock).

- **Client-Related Data**: Links clients to assets, including `direct_owner` (client name or ID), `ownership_type`, `position_id`, and values like balance or units.
- Attributes: Can include custom fields like risk indicators or demographic tags on the position level.
- Other: `value`, `unit_price`, `units`, `as_at` (valuation date), `stage` (e.g., "OWNED").

### Portfolio API
Handles aggregated portfolio views and queries for performance and holdings.

- **Client/Portfolio Data**:
  - Holdings and performance: `time_weighted_return` (TWR), asset allocations (e.g., by `asset_class` like "Equity" or "Cash").
  - Risk Metrics: Inferred through performance data or custom attributes (e.g., volatility via TWR calculations).
  - Groupings: By client, asset class, or custom attributes.
- Dynamic queries return data like returns in specific currencies, without needing saved views.

### Transactions API
Queries transaction history.

- **Client-Related Fields**: `client` (client reference), `direct_owner`, `account_name`, `comments`, `currency`, `cusip` (security identifier), `has_tax_lots`, `is_verified`.
- Financial Details: Accrued income, fees, trade dates, amounts.
- Filtering: By dates, attributes, or types (e.g., buys/sells).

### Additional APIs and Integrations
- **Client Portal API**: Publishes reports/files to clients, with fields like `files_id` and notification status.
- Integrations (e.g., Salesforce): Sync client data like household/client names, custom attributes (up to 10 per type), and entity mappings.
- **Position Relationships**: Manages owner-owned links, extending entity data.

| API | Key Client Data Examples | Notes |
|-----|---------------------------|-------|
| Entities | Name, type, creation dates, custom (risk profile, age) | Core for client profiles. |
| Attributes | Account number, asset class, custom demographics | Extensible for firm-specific fields. |
| Positions | Owner name/ID, value, units | Ownership links. |
| Portfolio | TWR, asset allocations | Performance and risk insights. |
| Transactions | Client ref, fees, tax lots | Historical activity. |

Data access depends on user permissions and OAuth scopes (e.g., `ENTITIES`, `PORTFOLIO`). Custom attributes enable personalization, but sensitive info like age/risk must be configured by the firm. For full details, refer to the Addepar Developer Portal.