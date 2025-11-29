# Technical Documentation: Bloom Oracle Integration
## 1. Overview
This document details the architecture and data flow of the Bloom Oracle system, a feature designed to enhance the creativity and reduce the repetitiveness of AI-generated text within the application.
The core problem being solved is that AI text generation, even with varied instructions, can fall into predictable patterns. The Bloom Oracle system introduces a controlled element of randomness and inspiration by "seeding" the AI's context with a unique, thematically relevant set of words for each generation request.
## 2. High-Level Architecture & Data Flow
The system integrates several modules to achieve this goal. The flow begins with the end-user in the `entgen` module and involves the `gencfg`, `bloora`, and `dict` modules on the backend.




### System Flow Diagram

```mermaid
flowchart TD
  U[User @ entgen UI]
  E[entity_gen.php: generateField()]
  GAPI[generate.php API]
  GS[GeneratorService.php: generate()]
  BloomPHP[Bloom.php]
  BloomOracle[bloom_oracle.php]
  PyAPI[/Python API: /bloom/generate]
  DB[(DB: Lemmas)]
  AIProv[AIProvider.php]
  ExtAI[(External AI API)]

  U -->|1. Clicks "Generate Description"| E
  E -->|AJAX request with config_id| GAPI
  GAPI -->|2. Loads GeneratorConfig| GS
  GS -->|3. Detects `oracle_config` is enabled| GS_decision{oracle_config?}
  GS_decision -- Yes -->|4. Calls Bloom Oracle Service| BloomPHP
  BloomPHP -->|HTTP Request| BloomOracle
  BloomOracle -->|HTTP Request| PyAPI
  PyAPI -->|Queries DB for lemmas| DB
  DB -->|Returns word list| PyAPI
  PyAPI -->|5. Returns JSON hint (Bloom filter + cleartext word list)| BloomOracle
  BloomOracle -->|5. Forwards JSON hint| BloomPHP
  BloomPHP -->|6. Returns/injects inspirational words into AI prompt| GS
  GS -->|6. Injects words into AI prompt| AIProv
  AIProv -->|7. Sends enriched prompt to External AI API| ExtAI
  ExtAI -->|8. Returns creative text| GS
  GS -->|8. Receives creative text and populates form field| E
```


### Detailed Step-by-Step Data Flow
1.  **User Action**: The user is on the `entity_gen.php` page and clicks the "Generate Description" button associated with a generator. The JavaScript `generateField()` function is triggered.
2.  **API Request**: An AJAX request is sent to `public/api/generate.php`. This request includes the `config_id` of the selected generator and a new `random_oracle_seed` to ensure a unique hint for every click.
3.  **Load Configuration**: The `generate.php` endpoint loads the corresponding `GeneratorConfig` entity from the database. It then calls the `GeneratorService`.
4.  **Oracle Check**: Inside `GeneratorService::generate()`, the service inspects the `GeneratorConfig` object. It finds a non-null `oracle_config` property, indicating that creative seeding is enabled for this generator.
5.  **Hint Generation**:
    *   The `GeneratorService` instantiates the `App\Oracle\Bloom` class.
    *   It calls the `generateHint()` method, passing the dictionary IDs from the `oracle_config` and the `random_oracle_seed` from the user's request.
    *   The `Bloom.php` wrapper makes an HTTP POST request to the Python API endpoint (`/bloom/generate`).
    *   The Python service queries the database to select a random set of words from the specified dictionaries, using the provided seed for deterministic randomness.
    *   The Python service constructs a Bloom filter from these words and returns a JSON object containing both the filter data and a cleartext array of the sampled words (`sampled_lemmas`).
6.  **Prompt Enrichment**: The `GeneratorService` receives this JSON response. It extracts the `sampled_lemmas` array and injects it into the system prompt for the AI, framed as an "Inspirational Hint."
7.  **AI Call**: The enriched prompt, now containing a unique list of inspirational words, is passed to the `AIProvider`, which sends it to the external AI API (e.g., OpenAI, Cohere).
8.  **Creative Response**: The AI generates text that is subtly influenced by the provided word list, resulting in a more unique and creative output. This response is sent back through the chain to the user's browser, where the description field is populated.
## 3. Database Schema
A new JSON column was added to the `generator_config` table to support this feature.
-   **Table**: `generator_config`
-   **Column**: `oracle_config`
-   **Type**: `JSON`
-   **Purpose**: Stores the configuration for the Bloom Oracle for a specific generator. It can be `NULL` if the feature is disabled.
**Example `oracle_config` JSON structure:**
json
{
  "dictionary_ids": [1, 3],
  "num_words": 150,
  "error_rate": 0.01
}
---
### Module-Specific Documentation
Here are the detailed documentation files for each individual module.
#### `gencfg` Module Documentation
# Module Documentation: Generator Configuration (`gencfg`)
## 1. Purpose
The `gencfg` module is the control center for all AI text generation tasks. It allows administrators to create, configure, and manage "Generators." With the new integration, it is now also responsible for configuring the Bloom Oracle for any given generator.
## 2. Components
-   **Database Table**: `generator_config`
-   **Doctrine Entity**: `src/Entity/GeneratorConfig.php`
-   **Admin UI**: `public/generator_admin.php`
-   **Backend API**: `public/generator_actions.php`
-   **Core Logic**: `src/Service/GeneratorService.php`
## 3. Role in the Bloom Oracle Flow
The `gencfg` module acts as the configuration layer for the entire system. It determines *if* and *how* the Bloom Oracle should be used for a specific generation task.
### Key Logic & Changes
#### A. Database & Entity (`generator_config`, `GeneratorConfig.php`)
-   A new nullable JSON column, `oracle_config`, has been added to the `generator_config` table.
-   The `GeneratorConfig` entity has been updated with a corresponding `?array $oracleConfig` property, along with `getOracleConfig()` and `setOracleConfig()` methods. This makes the oracle configuration a persistent part of every generator.
#### B. Admin UI (`generator_admin.php`)
-   **UI Enhancement**: The "Create/Edit Generator" modal now includes a "Creative Oracle (Optional)" section. This section contains:
    -   A multi-select dropdown to choose source dictionaries, populated via an API call.
    -   Input fields for "Words to Sample" and "Error Rate."
-   **JavaScript Logic**:
    -   `loadDictionaries()`: A new function that calls the `get_dictionaries` action in `generator_actions.php` to populate the dictionary selector on page load.
    -   `saveGenerator()`: This function now gathers the selected dictionary IDs and other oracle parameters. If any dictionaries are selected, it constructs an `oracle_config` object and includes it in the data sent to the backend. If none are selected, it sends `null`.
    -   `openEditModal()`: This function now reads the `oracle_config` from the fetched generator data and populates the oracle form fields accordingly, allowing for easy editing.
#### C. Backend API (`generator_actions.php`)
-   **`get_dictionaries` Action**: A new API action was added to provide a list of all available dictionaries (ID and Title) to the admin UI.
    -   **Critical Fix**: This action now correctly instantiates `DictionaryManager` using the global `$pdo` object (`new DictionaryManager($pdo)`), which is a pure `PDO` instance, resolving the type mismatch with Doctrine's DBAL Connection wrapper.
-   **`create` / `update` Actions**: These actions now check for the presence of an `oracle_config` key in the incoming JSON payload and save it to the corresponding property on the `GeneratorConfig` entity before flushing to the database.
-   **`get` Action**: This action was updated to include the `oracle_config` in its JSON response, so the admin UI's JavaScript can correctly populate the form when editing an existing generator.
#### D. Core Logic (`GeneratorService.php`)
This is where the configuration is acted upon.
-   The `generate()` method now checks if `$config->getOracleConfig()` returns a valid configuration.
-   If so, it instantiates `App\Oracle\Bloom` and calls `generateHint()`, passing in the configured parameters.
-   The returned hint (containing the `sampled_lemmas`) is passed to the `buildSystemMessage()` method.
-   `buildSystemMessage()` appends a new instruction to the system prompt, containing the list of inspirational words, before it's sent to the AI.
-   The service includes robust error handling: if the Bloom Oracle service fails for any reason, it logs the error but proceeds with the generation using the original prompt, ensuring the system is resilient.
---
#### `entgen` Module Documentation
# Module Documentation: Entity Generation UI (`entgen`)
## 1. Purpose
The `entgen` module provides the primary user-facing interface for creating and editing entities like characters, locations, and sketches. Its main feature is the AI-powered generation of names and descriptions via an AJAX-driven form.
## 2. Components
-   **UI Form**: `public/entity_gen.php`
-   **Generation API**: `public/api/generate.php`
## 3. Role in the Bloom Oracle Flow
The `entgen` module is the trigger for the entire creative generation process. While the UI itself remains simple, its JavaScript logic was updated to support the dynamic nature of the Bloom Oracle.
### Key Logic & Changes
#### A. Generation Info Endpoint (`generate.php`)
-   The `_info=1` mode of the API endpoint was modified. It now includes a boolean flag, `uses_oracle`, in its response. This flag is `true` if the requested `GeneratorConfig` has a non-null `oracle_config`. This tells the frontend whether it needs to provide an extra seed.
#### B. Frontend JavaScript (`entity_gen.php`)
-   The `generateField(fieldName)` function contains the core change.
-   After fetching the generator information (the `infoData` object), the script now checks for the `infoData.config.uses_oracle` flag.
-   **Dynamic Seeding**: If `uses_oracle` is true, the script adds a new parameter to the generation request payload:
    javascript
    params.random_oracle_seed = Math.floor(Math.random() * 10000000);
    -   **Significance**: This is a crucial step. By sending a new, random seed *specifically for the oracle* with every single click, we guarantee that the `GeneratorService` will request a fresh set of inspirational words from the Bloom Oracle each time. This maximizes variety and ensures the user never gets the same creative hint twice in a row, directly combating repetition. The UI remains unchanged, and this logic is completely transparent to the end-user.
---
#### `bloora` Module Documentation
# Module Documentation: Bloom Oracle (`bloora`)
## 1. Purpose
The `bloora` module is a microservice responsible for generating "creative hints" for the AI. It takes a set of source dictionaries and other parameters, samples a random collection of words, and packages them into a structured JSON response containing both a probabilistic Bloom Filter representation and a cleartext list of the words.
## 2. Components
-   **PHP Wrapper/Client**: `src/Oracle/Bloom.php`
-   **PHP API Endpoint**: `public/bloom_oracle.php`
-   **Python Service Endpoint**: `pyapi/services/bloom_service.py`
## 3. Role in the Bloom Oracle Flow
This module is the engine that provides the inspirational word lists. It decouples the complex task of word sampling and filter creation from the main `GeneratorService`.
### Key Logic & Changes
#### A. PHP Wrapper (`src/Oracle/Bloom.php`)
-   This class acts as a simple client for the Python microservice.
-   The `generateHint()` method takes parameters (dictionary IDs, word count, seed), constructs a JSON payload, and makes an HTTP POST request to the Python API.
-   It includes error handling to manage connection failures or non-successful HTTP status codes from the Python service, throwing exceptions that can be caught by the `GeneratorService`.
#### B. PHP API Endpoint (`public/bloom_oracle.php`)
-   This script serves as a simple, public-facing proxy to the `Bloom` class.
-   It handles incoming web requests, validates required parameters (`dictionary_ids`, `num_words`, etc.), and calls the `Bloom::generateHint()` method.
-   It formats the final output as a JSON response.
#### C. Python Service (`pyapi/services/bloom_service.py`)
This is the core of the hint generation logic.
-   **Database Connection**: It uses a dedicated `db_connector` to connect to the application's MySQL database to read lemma data.
-   **Seeded Randomness**: The service was updated to handle an optional `seed` parameter.
    -   If a `seed` is provided, the SQL query uses `ORDER BY RAND(?)` with the seed as a parameter. This makes the "random" word selection deterministic and reproducible for a given seed.
    -   If no `seed` is provided, it uses `ORDER BY RAND()`, resulting in a truly random selection.
-   **Word Sampling**: It executes a SQL query to select `num_words` distinct lemmas from the union of all specified `dictionary_ids`.
-   **Response Payload**: The response model was updated to include `sampled_lemmas`. The final JSON response now contains two key parts:
    1.  `bloom`: The probabilistic data structure (bit array, hash count).
    2.  `meta.sampled_lemmas`: A cleartext JSON array of the exact words that were sampled and added to the filter. This is the part that `GeneratorService` uses to enrich the AI prompt.
---
#### `dict` Module Documentation
# Module Documentation: Dictionaries (`dict`)
## 1. Purpose
The `dict` module is a foundational data module responsible for the ingestion, storage, and management of vocabularies (dictionaries). It parses text and PDF files to extract unique words (lemmas) and associates them with one or more named dictionaries.
## 2. Components
-   **Database Tables**: `dict_dictionaries`, `dict_lemmas`, `dict_lemma_2_dictionary`, `dict_source_files`.
-   **Core Logic**: `src/Dictionary/DictionaryManager.php`, `src/Dictionary/TextParser.php`.
-   **Admin UIs**: `public/dictionaries_admin.php`, `public/dictionary_parse.php`, etc.
## 3. Role in the Bloom Oracle Flow
The `dict` module serves as the **source of truth** for the entire creative seeding system. It provides the raw material (the words) that the Bloom Oracle samples from.
The quality and variety of the dictionaries within this module directly impact the quality and variety of the creative hints generated by the `bloora` module. For example:
-   A dictionary created from the works of a specific author (e.g., H.P. Lovecraft) will allow the Oracle to generate hints that are thematically aligned with cosmic horror.
-   A dictionary from a technical manual will produce hints with a scientific or mechanical flavor.
The `dict` module itself was not changed during this integration, but its importance has been elevated. It is now a critical component of the creative generation pipeline. The `generator_actions.php` API endpoint now uses `DictionaryManager` to provide a list of these dictionaries to the generator configuration UI.