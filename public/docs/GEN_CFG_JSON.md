# Generator Config - JSON Schema Specification

## Overview

This document provides a complete specification of the JSON configuration format used by the Generator Config system. The configuration defines how AI models should behave when generating content.

## Complete Configuration Structure

```json
{
  "system": {
    "role": "string",
    "instructions": ["string", "string", ...]
  },
  "parameters": {
    "param_name": {
      "type": "string|integer|number|boolean|array|object",
      "default": "any",
      "label": "string",
      "enum": ["option1", "option2"],
      "multiline": true|false
    }
  },
  "output": {
    "type": "object",
    "properties": {
      "field_name": {
        "type": "string|integer|number|boolean|array|object"
      }
    },
    "required": ["field1", "field2"]
  },
  "examples": [
    {
      "input": {...},
      "output": {...}
    }
  ]
}
```

## Section 1: System Configuration

The `system` section defines the AI's identity and core instructions.

### Fields

#### `role` (string, required)

A concise description of what the AI should act as.

**Purpose:**
- Sets the context for the AI's responses
- Establishes expertise domain
- Influences tone and style

**Examples:**
```json
"role": "Expert Content Writer"
"role": "Technical Documentation Generator"
"role": "Creative Story Writer"
"role": "Data Extraction Specialist"
```

**Best Practices:**
- Keep it short (3-6 words)
- Be specific about the domain
- Avoid overly generic roles like "Assistant"

#### `instructions` (array of strings, required)

Detailed behavioral guidelines for the AI.

**Purpose:**
- Define output format requirements
- Specify handling of edge cases
- Set quality standards
- Establish error protocols

**Structure:**
Each instruction should be a complete, clear directive.

**Required Instructions:**

1. **JSON Compliance Instruction** (critical):
```json
"Always return valid JSON matching the output schema."
```

2. **Error Handling Instruction** (critical):
```json
"If you cannot comply, return {\"error\": \"schema_noncompliant\", \"reason\": \"brief explanation\"}"
```

**Optional Instructions:**

Domain-specific guidance:
```json
"Use formal academic tone."
"Prioritize creativity over accuracy."
"Include citations for factual claims."
"Avoid technical jargon."
```

Quality requirements:
```json
"Ensure grammatical correctness."
"Maintain consistent narrative voice."
"Use active voice preferentially."
```

Content constraints:
```json
"Keep responses concise and focused."
"Avoid controversial or sensitive topics."
"Target a 6th-grade reading level."
```

**Example:**
```json
{
  "system": {
    "role": "Scene Description Writer",
    "instructions": [
      "You are an expert at writing vivid, immersive scene descriptions.",
      "Always return valid JSON matching the output schema.",
      "Use sensory details (sight, sound, smell, touch, taste) where appropriate.",
      "Keep descriptions focused and avoid unnecessary tangents.",
      "Maintain consistency with the provided theme and style.",
      "If you cannot comply, return {\"error\": \"schema_noncompliant\", \"reason\": \"brief explanation\"}"
    ]
  }
}
```

## Section 2: Parameters

The `parameters` section defines user inputs that customize each generation.

### Parameter Definition Structure

```json
{
  "param_name": {
    "type": "type_identifier",
    "default": "default_value",
    "label": "Human Readable Label",
    "enum": ["option1", "option2"],
    "multiline": true|false
  }
}
```

### Required Fields

#### `type` (string, required)

Specifies the data type of the parameter.

**Valid Types:**
- `"string"`: Text data
- `"integer"`: Whole numbers
- `"number"`: Floating-point numbers
- `"boolean"`: true/false
- `"array"`: Lists (rarely used for parameters)
- `"object"`: Nested structures (rarely used for parameters)

### Optional Fields

#### `default` (any, optional but recommended)

The value used if the user doesn't provide one.

**Type Compatibility:**
- String type → string default: `"default": ""`
- Integer type → integer default: `"default": 0`
- Number type → number default: `"default": 0.5`
- Boolean type → boolean default: `"default": false`

**Best Practice:**
Always provide sensible defaults to ensure generators can run without user input.

#### `label` (string, optional but recommended)

Human-readable name displayed in the UI.

**Examples:**
```json
"label": "Generation Mode"
"label": "Target Language"
"label": "Content Theme"
"label": "Difficulty Level"
```

**Best Practice:**
Use title case and descriptive, concise labels (2-4 words).

#### `enum` (array of strings, optional)

Restricts input to a predefined set of options. Only valid for `type: "string"`.

**Purpose:**
- Create dropdown selections
- Enforce valid choices
- Simplify user experience

**Example:**
```json
{
  "mode": {
    "type": "string",
    "enum": ["beginner", "intermediate", "advanced"],
    "default": "intermediate",
    "label": "Difficulty Level"
  }
}
```

**Best Practice:**
- Provide 2-8 options
- Use lowercase, hyphenated values
- Make the default a middle-ground option

#### `multiline` (boolean, optional)

For string types, renders as textarea instead of input. Default: `false`.

**Use Cases:**
- Long text input (descriptions, topics, prompts)
- Multi-sentence requirements
- Formatted text

**Example:**
```json
{
  "description": {
    "type": "string",
    "default": "",
    "label": "Scene Description",
    "multiline": true
  }
}
```

### Complete Parameter Examples

#### String with Enum (Dropdown)
```json
{
  "language": {
    "type": "string",
    "enum": ["english", "german", "french", "spanish"],
    "default": "english",
    "label": "Output Language"
  }
}
```

#### String Multiline (Textarea)
```json
{
  "topic": {
    "type": "string",
    "default": "",
    "label": "Content Topic",
    "multiline": true
  }
}
```

#### Integer (Number Input)
```json
{
  "word_count": {
    "type": "integer",
    "default": 500,
    "label": "Target Word Count"
  }
}
```

#### Number/Float (Decimal Input)
```json
{
  "creativity": {
    "type": "number",
    "default": 0.7,
    "label": "Creativity Level"
  }
}
```

### Real-World Parameter Set Examples

#### Creative Writing Generator
```json
{
  "parameters": {
    "genre": {
      "type": "string",
      "enum": ["fantasy", "sci-fi", "mystery", "romance", "horror"],
      "default": "fantasy",
      "label": "Story Genre"
    },
    "length": {
      "type": "string",
      "enum": ["short", "medium", "long"],
      "default": "medium",
      "label": "Story Length"
    },
    "prompt": {
      "type": "string",
      "default": "",
      "label": "Story Prompt",
      "multiline": true
    }
  }
}
```

#### Technical Documentation Generator
```json
{
  "parameters": {
    "language": {
      "type": "string",
      "enum": ["python", "javascript", "java", "go", "rust"],
      "default": "python",
      "label": "Programming Language"
    },
    "detail_level": {
      "type": "string",
      "enum": ["brief", "standard", "comprehensive"],
      "default": "standard",
      "label": "Documentation Detail"
    },
    "include_examples": {
      "type": "boolean",
      "default": true,
      "label": "Include Code Examples"
    }
  }
}
```

## Section 3: Output Schema

The `output` section defines the structure of the AI's response using JSON Schema syntax.

### Schema Root

Must be an object type:
```json
{
  "output": {
    "type": "object",
    "properties": {...},
    "required": [...]
  }
}
```

### Properties Definition

Each property defines a field in the response:

```json
{
  "properties": {
    "field_name": {
      "type": "string|integer|number|boolean|array|object",
      "properties": {...},    // for nested objects
      "items": {...}          // for arrays
    }
  }
}
```

### Type Specifications

#### String Fields
```json
{
  "title": {
    "type": "string"
  }
}
```

**Validation:**
- Checks that value is a string
- Does not validate length or format

#### Integer Fields
```json
{
  "count": {
    "type": "integer"
  }
}
```

**Validation:**
- Checks that value is an integer
- Rejects floats and strings

#### Number Fields
```json
{
  "score": {
    "type": "number"
  }
}
```

**Validation:**
- Accepts integers and floats
- Rejects strings

#### Boolean Fields
```json
{
  "success": {
    "type": "boolean"
  }
}
```

**Validation:**
- Checks that value is true or false
- Rejects truthy/falsy values

#### Array Fields

Simple arrays:
```json
{
  "tags": {
    "type": "array"
  }
}
```

Arrays with item type:
```json
{
  "tags": {
    "type": "array",
    "items": {
      "type": "string"
    }
  }
}
```

**Note:** Current validator checks array type but not item types.

#### Object Fields

Simple objects:
```json
{
  "metadata": {
    "type": "object"
  }
}
```

Nested objects with structure:
```json
{
  "metadata": {
    "type": "object",
    "properties": {
      "wordCount": {"type": "integer"},
      "author": {"type": "string"}
    }
  }
}
```

### Required Fields

Specify which fields must be present:

```json
{
  "output": {
    "type": "object",
    "properties": {
      "result": {"type": "string"},
      "metadata": {"type": "object"},
      "optional_field": {"type": "string"}
    },
    "required": ["result", "metadata"]
  }
}
```

**Validation:**
- Checks that all required fields exist
- Optional fields can be omitted
- Missing required fields cause validation failure

### Complex Schema Examples

#### Content Generation Output
```json
{
  "output": {
    "type": "object",
    "properties": {
      "content": {
        "type": "string"
      },
      "title": {
        "type": "string"
      },
      "metadata": {
        "type": "object",
        "properties": {
          "wordCount": {"type": "integer"},
          "readingTime": {"type": "integer"},
          "sentiment": {"type": "string"}
        }
      }
    },
    "required": ["content", "title", "metadata"]
  }
}
```

#### Structured Extraction Output
```json
{
  "output": {
    "type": "object",
    "properties": {
      "entities": {
        "type": "array",
        "items": {
          "type": "object",
          "properties": {
            "name": {"type": "string"},
            "type": {"type": "string"},
            "confidence": {"type": "number"}
          }
        }
      },
      "summary": {
        "type": "string"
      },
      "processed": {
        "type": "boolean"
      }
    },
    "required": ["entities", "summary", "processed"]
  }
}
```

#### Scene Description Output
```json
{
  "output": {
    "type": "object",
    "properties": {
      "scene": {
        "type": "string"
      },
      "beats": {
        "type": "array"
      },
      "theme": {
        "type": "string"
      },
      "style": {
        "type": "string"
      },
      "metadata": {
        "type": "object",
        "properties": {
          "sentenceCount": {"type": "integer"},
          "wordCount": {"type": "integer"}
        }
      }
    },
    "required": ["scene", "beats", "theme", "metadata"]
  }
}
```

## Section 4: Examples (Optional)

The `examples` section provides sample input/output pairs to guide the AI.

### Structure

```json
{
  "examples": [
    {
      "input": {...},
      "output": {...}
    },
    {
      "input": {...},
      "output": {...}
    }
  ]
}
```

### When to Use Examples

**Use examples when:**
- Output format is complex or unusual
- Specific phrasing is important
- Edge cases need demonstration
- Quality standards are hard to describe

**Skip examples when:**
- Schema is self-explanatory
- Output is simple text
- Flexibility is more important than consistency

### Example Structure

#### Input Object

Mirrors the parameter structure:
```json
{
  "input": {
    "mode": "detailed",
    "topic": "sunset over ocean",
    "language": "english"
  }
}
```

#### Output Object

Mirrors the output schema:
```json
{
  "output": {
    "result": "The sun descends slowly...",
    "metadata": {
      "wordCount": 45,
      "sentenceCount": 3
    }
  }
}
```

### Complete Example

```json
{
  "examples": [
    {
      "input": {
        "mode": "simple",
        "language": "english"
      },
      "output": {
        "twister": "She sells seashells by the seashore",
        "mode": "simple",
        "language": "english",
        "metadata": {
          "wordCount": 6,
          "firstLetter": "S"
        }
      }
    },
    {
      "input": {
        "mode": "complex",
        "language": "english"
      },
      "output": {
        "twister": "Peter Piper picked a peck of pickled peppers",
        "mode": "complex",
        "language": "english",
        "metadata": {
          "wordCount": 8,
          "firstLetter": "P"
        }
      }
    }
  ]
}
```

### Best Practices for Examples

1. **Provide 2-4 examples**: Enough to show variation, not too many to be overwhelming
2. **Show edge cases**: Demonstrate minimum and maximum complexity
3. **Use realistic data**: Examples should reflect actual use cases
4. **Match schema exactly**: Output structure must match your schema
5. **Vary parameters**: Show different parameter combinations

## Complete Configuration Examples

### Example 1: Tonguetwister Generator

```json
{
  "system": {
    "role": "Tonguetwister Creator",
    "instructions": [
      "You are an expert at creating challenging tongue twisters.",
      "Always return valid JSON matching the output schema.",
      "Use alliteration and similar sounds to create difficulty.",
      "Adjust complexity based on the mode parameter.",
      "If you cannot comply, return {\"error\": \"schema_noncompliant\", \"reason\": \"brief explanation\"}"
    ]
  },
  "parameters": {
    "mode": {
      "type": "string",
      "enum": ["easy", "medium", "hard"],
      "default": "medium",
      "label": "Difficulty Level"
    },
    "language": {
      "type": "string",
      "enum": ["english", "german", "spanish"],
      "default": "english",
      "label": "Language"
    }
  },
  "output": {
    "type": "object",
    "properties": {
      "twister": {
        "type": "string"
      },
      "mode": {
        "type": "string"
      },
      "language": {
        "type": "string"
      },
      "metadata": {
        "type": "object",
        "properties": {
          "wordCount": {"type": "integer"},
          "firstLetter": {"type": "string"}
        }
      }
    },
    "required": ["twister", "mode", "language", "metadata"]
  },
  "examples": []
}
```

### Example 2: Blog Post Generator

```json
{
  "system": {
    "role": "Professional Blog Writer",
    "instructions": [
      "You are an expert content writer specializing in engaging blog posts.",
      "Always return valid JSON matching the output schema.",
      "Write in a conversational yet professional tone.",
      "Include SEO-friendly headings and structure.",
      "Aim for the target word count specified by the user.",
      "If you cannot comply, return {\"error\": \"schema_noncompliant\", \"reason\": \"brief explanation\"}"
    ]
  },
  "parameters": {
    "topic": {
      "type": "string",
      "default": "",
      "label": "Blog Topic",
      "multiline": true
    },
    "tone": {
      "type": "string",
      "enum": ["casual", "professional", "academic", "humorous"],
      "default": "professional",
      "label": "Writing Tone"
    },
    "word_count": {
      "type": "integer",
      "default": 800,
      "label": "Target Word Count"
    }
  },
  "output": {
    "type": "object",
    "properties": {
      "title": {
        "type": "string"
      },
      "content": {
        "type": "string"
      },
      "excerpt": {
        "type": "string"
      },
      "tags": {
        "type": "array"
      },
      "metadata": {
        "type": "object",
        "properties": {
          "wordCount": {"type": "integer"},
          "readingTimeMinutes": {"type": "integer"}
        }
      }
    },
    "required": ["title", "content", "excerpt", "tags", "metadata"]
  },
  "examples": []
}
```

### Example 3: Code Documentation Generator

```json
{
  "system": {
    "role": "Technical Documentation Specialist",
    "instructions": [
      "You are an expert at writing clear, comprehensive code documentation.",
      "Always return valid JSON matching the output schema.",
      "Include parameter descriptions, return values, and usage examples.",
      "Use proper markdown formatting for code blocks.",
      "Match the documentation style to the programming language.",
      "If you cannot comply, return {\"error\": \"schema_noncompliant\", \"reason\": \"brief explanation\"}"
    ]
  },
  "parameters": {
    "language": {
      "type": "string",
      "enum": ["python", "javascript", "java", "go", "rust"],
      "default": "python",
      "label": "Programming Language"
    },
    "code_snippet": {
      "type": "string",
      "default": "",
      "label": "Code to Document",
      "multiline": true
    },
    "detail_level": {
      "type": "string",
      "enum": ["brief", "standard", "comprehensive"],
      "default": "standard",
      "label": "Documentation Level"
    }
  },
  "output": {
    "type": "object",
    "properties": {
      "summary": {
        "type": "string"
      },
      "parameters": {
        "type": "array"
      },
      "returns": {
        "type": "string"
      },
      "examples": {
        "type": "array"
      },
      "notes": {
        "type": "array"
      }
    },
    "required": ["summary", "parameters", "returns", "examples"]
  },
  "examples": []
}
```

## Validation and Error Handling

### Model-Signaled Errors

If the AI cannot generate compliant output, it should return:

```json
{
  "error": "schema_noncompliant",
  "reason": "Brief explanation of why compliance failed"
}
```

This is caught by the validator and treated as a validation error.

### Normalization

When validation fails, the `ResponseNormalizer` attempts to fix common issues:

1. **Missing fields**: Filled with defaults
2. **Wrong types**: Attempted type coercion
3. **Empty required fields**: Synthesized from available data
4. **Pattern-specific fixes**: Custom logic for known generator types

### Warnings

Normalizations generate warnings that appear in test results:
- "Could not find value for 'field_name'"
- "Scene text auto-generated from beats"
- "No twister found in response"

## Best Practices Summary

### System Configuration
- ✅ Be specific in the role definition
- ✅ Always include JSON compliance instruction
- ✅ Always include error handling instruction
- ✅ Add domain-specific quality requirements
- ❌ Don't make instructions too verbose
- ❌ Don't contradict schema requirements

### Parameters
- ✅ Provide defaults for all parameters
- ✅ Use descriptive labels
- ✅ Use enums for limited choices
- ✅ Use multiline for long text inputs
- ❌ Don't use overly technical parameter names
- ❌ Don't create too many required parameters

### Output Schema
- ✅ Mark all essential fields as required
- ✅ Use appropriate types
- ✅ Include metadata fields for computed info
- ✅ Keep structure flat when possible
- ❌ Don't over-specify nested structures
- ❌ Don't require fields that might not apply

### Examples
- ✅ Show realistic use cases
- ✅ Demonstrate parameter variations
- ✅ Match schema exactly
- ❌ Don't provide too many examples (2-4 is ideal)
- ❌ Don't use placeholder or dummy data

## JSON Validation

Before saving, ensure your configuration is valid JSON:

1. **Syntax check**: Use a JSON validator
2. **Schema validation**: Verify all required fields present
3. **Type consistency**: Ensure defaults match parameter types
4. **Completeness**: All sections properly defined

### Common JSON Errors

**Missing commas:**
```json
{
  "type": "string"  // ❌ Missing comma
  "default": "value"
}
```

**Trailing commas:**
```json
{
  "items": ["a", "b", "c",]  // ❌ Trailing comma
}
```

**Unquoted keys:**
```json
{
  type: "string"  // ❌ Key must be quoted
}
```

**Single quotes:**
```json
{
  'type': 'string'  // ❌ Must use double quotes
}
```

## Conclusion

This specification covers all aspects of the Generator Config JSON format. Use it as a reference when creating or modifying generator configurations. For implementation details, see the Technical Reference. For user-facing guidance, see the User Guide.
