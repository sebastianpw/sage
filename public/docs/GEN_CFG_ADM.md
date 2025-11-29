# Generator Config Admin - User Guide

## Overview

The Generator Config Admin is a web-based interface for creating and managing AI-powered content generators. Each generator is a configurable template that defines how AI models should behave when generating specific types of content.

## Getting Started

### Accessing the Admin Interface

Navigate to `/generator_admin.php` in your application. You must be authenticated to access this interface.

### Main Interface

The admin interface displays:
- **List of Generators**: All your created generators with their status
- **Create Button**: Add new generators
- **Generator Cards**: Each showing title, ID, model, type, and creation date

## Creating a Generator

### Step 1: Click "New Generator"

This opens the creation modal with a default template.

### Step 2: Configure Basic Settings

**Title**: A descriptive name for your generator (e.g., "Scene Generator", "Tonguetwister Creator")

**Model**: Choose the AI model to use:
- **Pollinations Free Models**: `openai`, `openai-fast`, `chickytutor`
- **Google Gemini**: Various Gemini models for advanced tasks
- **Mistral**: High-performance models
- **Groq**: Fast inference models
- **Cohere**: Command series models

**Visibility**: Controls where the generator appears:
- **Floatool**: Only in the Floatool interface
- **Internal Only**: Only for internal use
- **Everywhere**: Available in all interfaces

### Step 3: Define Configuration JSON

The configuration JSON has four main sections:

#### System Section
Defines the AI's role and behavior:
```json
{
  "system": {
    "role": "Content Generator",
    "instructions": [
      "You are an expert content generator.",
      "Always return valid JSON matching the output schema.",
      "If you cannot comply, return {\"error\": \"schema_noncompliant\", \"reason\": \"brief explanation\"}"
    ]
  }
}
```

#### Parameters Section
Defines user inputs for your generator:
```json
{
  "parameters": {
    "mode": {
      "type": "string",
      "enum": ["simple", "detailed"],
      "default": "simple",
      "label": "Generation Mode"
    },
    "topic": {
      "type": "string",
      "default": "",
      "label": "Topic",
      "multiline": true
    }
  }
}
```

#### Output Section
Defines the expected response structure:
```json
{
  "output": {
    "type": "object",
    "properties": {
      "result": { "type": "string" },
      "metadata": { "type": "object" }
    },
    "required": ["result", "metadata"]
  }
}
```

#### Examples Section (Optional)
Provide example inputs/outputs to guide the AI.

### Step 4: Save

Click "Save Generator" to create your generator.

## Testing a Generator

### Running a Test

1. Click the **"⚗️ Test"** button on any generator
2. Fill in the parameter values in the test modal
3. Click **"⚗️ Generate"** to run the test
4. View the JSON response
5. Use **"📋 Copy"** to copy the result

### Understanding Test Results

The test result shows:
- **data**: The validated, normalized output
- **schema_valid**: Whether the output matched your schema
- **validation_errors**: Any schema violations
- **warnings**: Issues that were automatically corrected
- **elapsed_ms**: Generation time in milliseconds
- **model**: Which model was used

## Managing Generators

### Editing

1. Click **"Edit"** on any generator
2. Modify the configuration
3. Click **"Save Generator"**

### Enabling/Disabling

Click **"Enable"** or **"Disable"** to toggle generator availability without deleting it.

### Deleting

Click **"Delete"** and confirm to permanently remove a generator.

## Common Use Cases

### 1. Content Generation
Create generators for articles, stories, or creative writing with parameters for style, length, and tone.

### 2. Structured Data Extraction
Build generators that extract specific information from text and return it in a consistent format.

### 3. Translation and Localization
Configure generators for translating content with parameters for source/target language and formality.

### 4. Code Generation
Design generators that produce code snippets based on requirements and programming language.

### 5. Creative Writing Exercises
Build generators for writing prompts, twisters, riddles, or other creative exercises.

## Best Practices

### Clear Instructions
- Be specific about what the AI should do
- Include format requirements in system instructions
- Specify how to handle edge cases

### Parameter Design
- Use meaningful labels
- Provide sensible defaults
- Use enums for limited choices
- Mark multiline fields for longer text inputs

### Schema Definition
- Define all expected output fields
- Mark required fields explicitly
- Use appropriate types (string, integer, array, object)
- Include metadata fields for computed information

### Testing
- Test with various parameter combinations
- Verify edge cases
- Check that error handling works
- Ensure output is consistent

## Troubleshooting

### Generator Not Appearing
- Check the "Visibility" setting (type field)
- Ensure the generator is enabled (active status)
- Verify you're looking in the correct interface

### Invalid JSON Errors
- Validate your JSON syntax
- Ensure all brackets and braces are balanced
- Check for missing commas
- Use the browser console for detailed error messages

### Schema Validation Failures
- The AI's output didn't match your schema
- Check validation_errors in test results
- The normalizer may auto-correct some issues
- Review warnings to understand what was fixed

### Test Timeouts
- Some models are slower than others
- Large outputs take longer to generate
- Check your max_tokens setting if specified
- Consider using a faster model for testing

## Tips for Success

1. **Start Simple**: Begin with basic generators and add complexity gradually
2. **Use Examples**: Provide examples in your config to guide the AI
3. **Test Thoroughly**: Run multiple tests before deploying
4. **Monitor Results**: Check the validation and warnings sections
5. **Iterate**: Refine instructions based on actual results
6. **Document Parameters**: Use clear, descriptive labels
7. **Plan for Errors**: Include instructions for handling invalid inputs

## Next Steps

- Explore the Technical Reference for implementation details
- Review the JSON Schema Documentation for advanced configuration
- Check existing generators for inspiration
- Experiment with different models and parameters
