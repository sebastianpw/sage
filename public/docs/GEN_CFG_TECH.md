# Generator Config - Technical Reference

## Architecture Overview

The Generator Config system consists of several interconnected components:

### Core Components

1. **GeneratorConfig Entity** (`src/Entity/GeneratorConfig.php`)
   - Doctrine ORM entity for database persistence
   - Stores configuration as JSON fields
   - Manages metadata (user_id, timestamps, active status)

2. **GeneratorService** (`src/Service/GeneratorService.php`)
   - Core generation logic
   - Builds prompts from configuration
   - Handles AI communication
   - Validates and normalizes responses

3. **SchemaValidator** (`src/Service/Schema/SchemaValidator.php`)
   - Validates AI responses against output schema
   - Checks required fields and types
   - Returns structured validation results

4. **ResponseNormalizer** (`src/Service/Schema/ResponseNormalizer.php`)
   - Attempts to fix non-compliant responses
   - Type-specific normalization strategies
   - Generates warnings for corrections

5. **AIProvider** (`src/Core/AIProvider.php`)
   - Unified interface for multiple AI providers
   - Handles authentication and routing
   - Supports Cohere, Mistral, Groq, Gemini, Pollinations

## Database Schema

### Table: `generator_config`

```sql
CREATE TABLE `generator_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL DEFAULT 'openai',
  `system_role` text NOT NULL,
  `instructions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`instructions`)),
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`parameters`)),
  `output_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`output_schema`)),
  `examples` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`examples`)),
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'floatool',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_id` (`config_id`),
  KEY `user_id` (`user_id`),
  KEY `active` (`active`),
  KEY `idx_user_active` (`user_id`,`active`),
  KEY `idx_type` (`type`)
);
```

### Key Fields

- **config_id**: Unique 32-character hex identifier
- **user_id**: Owner of the configuration
- **title**: Human-readable name
- **model**: AI model identifier (e.g., 'openai', 'gemini-2.5-flash')
- **type**: Visibility scope ('floatool', 'internal', 'all')
- **active**: Boolean flag for enabling/disabling
- **system_role**: AI's role description
- **instructions**: JSON array of instruction strings
- **parameters**: JSON object defining user inputs
- **output_schema**: JSON schema for response validation
- **examples**: Optional JSON array of example interactions

## API Endpoints

### Base URL: `/generator_actions.php`

All requests should be POST with JSON body containing an `action` field.

### Actions

#### 1. List Generators
```json
{
  "action": "list"
}
```

**Response:**
```json
{
  "ok": true,
  "data": [
    {
      "id": 1,
      "config_id": "abc123...",
      "title": "Scene Generator",
      "model": "openai",
      "type": "floatool",
      "active": true,
      "created_at": "2025-01-15 10:30"
    }
  ]
}
```

#### 2. Get Generator
```json
{
  "action": "get",
  "id": 1
}
```

**Response:**
```json
{
  "ok": true,
  "data": {
    "id": 1,
    "title": "Scene Generator",
    "model": "openai",
    "type": "floatool",
    "config_json": "{...}"
  }
}
```

#### 3. Create Generator
```json
{
  "action": "create",
  "title": "New Generator",
  "model": "openai",
  "type": "floatool",
  "config_json": "{...}"
}
```

**Response:**
```json
{
  "ok": true,
  "message": "Generator created: New Generator",
  "data": {
    "id": 2,
    "config_id": "def456..."
  }
}
```

#### 4. Update Generator
```json
{
  "action": "update",
  "id": 1,
  "title": "Updated Title",
  "model": "gemini-2.5-flash",
  "type": "all",
  "config_json": "{...}"
}
```

#### 5. Delete Generator
```json
{
  "action": "delete",
  "id": 1
}
```

#### 6. Toggle Active Status
```json
{
  "action": "toggle",
  "id": 1
}
```

#### 7. Test Generator
```json
{
  "action": "test",
  "id": 1,
  "params": {
    "mode": "detailed",
    "topic": "sunset"
  }
}
```

**Response:**
```json
{
  "ok": true,
  "result": {
    "ok": true,
    "data": {...},
    "raw_response": "...",
    "decoded": {...},
    "schema_valid": true,
    "validation_errors": [],
    "warnings": [],
    "elapsed_ms": 1234,
    "model": "openai",
    "request_messages": [...]
  }
}
```

#### 8. Get Available Models
```json
{
  "action": "get_models"
}
```

**Response:**
```json
{
  "ok": true,
  "data": ["openai", "openai-fast", "gemini-2.5-flash", ...]
}
```

## Generation Flow

### 1. Request Initiation

```php
$service = new GeneratorService($aiProvider, $validator, $normalizer, $logger);
$result = $service->generate($config, $params, $aiOptions);
```

### 2. Message Construction

The service builds a conversation:

**System Message:**
```
{role}

{instruction1}
{instruction2}
...
```

**User Message:**
```json
{
  "input": {
    "param1": "value1",
    "param2": "value2"
  }
}
```

### 3. AI Provider Call

```php
$rawResponse = $aiProvider->sendMessage(
    $config->getModel(),
    $messages,
    $aiOptions
);
```

### 4. Response Extraction

The service extracts JSON from the response using:
- Direct JSON decode
- Regex pattern matching for JSON objects
- Balanced brace extraction

### 5. Validation

```php
$validation = $validator->validate($decoded, $config->getOutputSchema());
```

Checks:
- Required fields present
- Field types match schema
- No model-signaled errors (`error: "schema_noncompliant"`)

### 6. Normalization (if needed)

```php
$normResult = $normalizer->normalize($decoded, $schema, $userInput);
```

Attempts:
- Type coercion
- Default value injection
- Pattern-specific fixes (tonguetwister, scene, etc.)
- Metadata computation

### 7. Result Assembly

```php
return new GeneratorResult(
    success: $normalized !== null,
    data: $normalized,
    rawResponse: $rawResponse,
    decoded: $decoded,
    validation: $validation,
    warnings: $warnings,
    elapsedMs: (int)($elapsed * 1000),
    model: $config->getModel(),
    requestMessages: $messages
);
```

## Parameter Type System

### Supported Parameter Types

#### String
```json
{
  "param_name": {
    "type": "string",
    "default": "default_value",
    "label": "Display Label",
    "multiline": false
  }
}
```

#### String with Enum
```json
{
  "mode": {
    "type": "string",
    "enum": ["option1", "option2", "option3"],
    "default": "option1",
    "label": "Mode"
  }
}
```

#### Integer
```json
{
  "count": {
    "type": "integer",
    "default": 5,
    "label": "Item Count"
  }
}
```

#### Number (Float)
```json
{
  "temperature": {
    "type": "number",
    "default": 0.7,
    "label": "Temperature"
  }
}
```

### UI Rendering

Parameters are automatically rendered as:
- **Text input**: String without enum
- **Textarea**: String with `multiline: true`
- **Select dropdown**: String with `enum` array
- **Number input**: Integer or number type

## Schema Validation System

### Output Schema Structure

Based on JSON Schema specification:

```json
{
  "type": "object",
  "properties": {
    "field1": {
      "type": "string"
    },
    "field2": {
      "type": "array"
    },
    "field3": {
      "type": "object"
    }
  },
  "required": ["field1", "field3"]
}
```

### Supported Types

- **string**: Text data
- **integer**: Whole numbers
- **number**: Floating point numbers
- **boolean**: true/false
- **array**: Lists
- **object**: Nested structures

### Validation Process

1. Check for null data
2. Check for model-signaled errors
3. Verify required fields exist
4. Validate field types
5. Return ValidationResult with errors

## Normalization Strategies

### Generic Normalization

For unknown generator types:
1. Copy existing fields from response
2. Fill missing fields with defaults
3. Use user input as fallback
4. Use schema defaults as last resort

### Pattern-Specific Normalization

#### Tonguetwister Pattern
Detected when schema has: `twister`, `mode`, `language`

Actions:
- Search for twister text in multiple fields
- Extract alternatives into metadata
- Compute word count and first letter
- Handle array or string variations

#### Scene Pattern
Detected when schema has: `scene`, `beats`, `theme`

Actions:
- Normalize scene text
- Parse beats from JSON string if needed
- Synthesize scene from beats if empty
- Compute sentence and word counts
- Ensure proper array/object types

### Metadata Computation

Normalizers can compute derived fields:
- **wordCount**: `countWords()` using Unicode-aware regex
- **sentenceCount**: `countSentences()` using punctuation detection
- **firstLetter**: `getFirstLetter()` using Unicode property matching

## AI Provider Integration

### Model Selection

The `AIProvider` routes requests based on model identifier:

```php
if ($this->isCohereModel($model)) {
    return $this->sendToCohereApi(...);
} elseif ($this->isMistralModel($model)) {
    return $this->sendToMistralApi(...);
} elseif ($this->isGroqModel($model)) {
    return $this->sendToGroqApi(...);
}
// ... etc
```

### Authentication

API keys are resolved in order:
1. Environment variables (e.g., `COHERE_API_KEY`)
2. Token files in `~/token/` directory

### Request Construction

Standard OpenAI-compatible format:
```json
{
  "model": "model-name",
  "messages": [
    {"role": "system", "content": "..."},
    {"role": "user", "content": "..."}
  ],
  "temperature": 0.7,
  "max_tokens": 1000
}
```

### Response Parsing

Expected format:
```json
{
  "choices": [
    {
      "message": {
        "content": "AI response text"
      }
    }
  ]
}
```

## Error Handling

### Levels of Error Handling

1. **Validation Errors**: Schema mismatches, missing fields
2. **Normalization Warnings**: Auto-corrected issues
3. **AI Provider Errors**: API failures, authentication issues
4. **System Errors**: Database failures, exceptions

### Error Response Format

```json
{
  "ok": false,
  "error": "Error message",
  "trace": "Stack trace (in development)"
}
```

### Model-Signaled Errors

AI can signal inability to comply:
```json
{
  "error": "schema_noncompliant",
  "reason": "Cannot generate the requested content"
}
```

This is caught by the validator and treated as a validation failure.

## Security Considerations

### Authentication
- All endpoints require `$_SESSION['user_id']`
- Unauthorized requests return 401

### Authorization
- Users can only access their own generators
- Cross-user access attempts fail with "Config not found"

### Input Validation
- JSON syntax validation on create/update
- SQL injection protection via Doctrine ORM
- XSS protection via proper output escaping

### API Key Security
- Keys stored outside web root
- Never exposed in responses
- Loaded from environment or token files

## Performance Optimization

### Caching Opportunities
- Model catalog (rarely changes)
- User's generator list (invalidate on modify)
- Validation schemas (immutable per config)

### Database Indexing
- Primary key on `id`
- Unique index on `config_id`
- Composite index on `(user_id, active)`
- Index on `type` for visibility filtering

### Query Optimization
- Load only necessary fields for list views
- Use `findBy` with ORDER BY for sorted results
- Eager loading for related entities

## Extension Points

### Adding New AI Providers

1. Add model constants to `AIProvider`
2. Implement detection method (e.g., `isNewProviderModel()`)
3. Implement API method (e.g., `sendToNewProviderApi()`)
4. Add authentication method (e.g., `getNewProviderApiKey()`)
5. Update model catalog in `getModelCatalog()`

### Custom Normalization Patterns

1. Add detection method to `ResponseNormalizer`
2. Implement `normalize{Pattern}()` method
3. Add pattern-specific metadata computation
4. Handle edge cases and type coercion

### Additional Parameter Types

1. Extend UI rendering in `renderTestParams()`
2. Add form control generation logic
3. Update parameter documentation
4. Test with various schema definitions

## Deployment Checklist

- [ ] Database migrations applied
- [ ] Environment variables configured
- [ ] API keys provisioned
- [ ] Token files in place (if using file-based auth)
- [ ] Web server permissions set
- [ ] Error logging configured
- [ ] User authentication working
- [ ] Test all AI providers
- [ ] Verify schema validation
- [ ] Check normalization patterns
- [ ] Monitor API rate limits
- [ ] Set up backup procedures
