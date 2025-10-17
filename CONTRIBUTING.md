# Contributing to SAGE

First off, thank you for considering contributing to SAGE! It's people like you that make SAGE such a great tool for the creative coding community.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Commit Guidelines](#commit-guidelines)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Enhancements](#suggesting-enhancements)
- [Community](#community)

---

## Code of Conduct

This project and everyone participating in it is governed by our commitment to providing a welcoming and inclusive environment. By participating, you are expected to uphold this standard.

### Our Standards

**Positive behaviors include:**
- Using welcoming and inclusive language
- Being respectful of differing viewpoints and experiences
- Gracefully accepting constructive criticism
- Focusing on what is best for the community
- Showing empathy towards other community members

**Unacceptable behaviors include:**
- Trolling, insulting/derogatory comments, and personal or political attacks
- Public or private harassment
- Publishing others' private information without explicit permission
- Other conduct which could reasonably be considered inappropriate

---

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

**Bug Report Template:**

```markdown
**Description:**
A clear and concise description of the bug.

**To Reproduce:**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '...'
3. Scroll down to '...'
4. See error

**Expected Behavior:**
What you expected to happen.

**Screenshots:**
If applicable, add screenshots to help explain your problem.

**Environment:**
- OS: [e.g., Android 13, Ubuntu 22.04]
- PHP Version: [e.g., 8.3.2]
- SAGE Version: [e.g., 1.2.0]
- Device: [e.g., Samsung Galaxy S23, Termux]

**Additional Context:**
Any other context about the problem.
```

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, please include:

**Enhancement Template:**

```markdown
**Feature Description:**
A clear and concise description of the feature.

**Use Case:**
Describe the problem this feature would solve or the workflow it would improve.

**Proposed Solution:**
How you envision this feature working.

**Alternatives Considered:**
Any alternative solutions or features you've considered.

**Additional Context:**
Screenshots, mockups, or examples from other projects.
```

### Your First Code Contribution

Unsure where to begin? Look for issues tagged with:
- `good-first-issue` - Issues suitable for newcomers
- `help-wanted` - Issues where we need community help
- `documentation` - Improvements to docs

### Areas Where We Need Help

- **Documentation**: Tutorials, API docs, setup guides
- **Testing**: Writing tests, testing on different devices
- **Features**: Implementing roadmap items
- **Bug Fixes**: Resolving reported issues
- **Translations**: Internationalizing the interface
- **Performance**: Optimizing for mobile/Termux environments

---

## Development Setup

### Prerequisites

- PHP 8.3+
- Composer
- MariaDB/MySQL
- Git
- Node.js (for frontend assets, if applicable)

### Local Setup

```bash
# Clone your fork
git clone https://github.com/YOUR-USERNAME/sage.git
cd sage

# Add upstream remote
git remote add upstream https://github.com/petersebring/sage.git

# Install dependencies
composer install

# Copy environment configuration
cp .env.example .env

# Configure your database in .env
# DB_HOST=localhost
# DB_NAME=sage_dev
# DB_USER=root
# DB_PASS=

# Set up database
php bin/console database:setup

# Start development server
php -S localhost:8080 -t public
```

### Development Workflow

```bash
# Create a feature branch
git checkout -b feature/amazing-feature

# Make your changes
# ... code code code ...

# Run tests (if available)
php bin/console test:run

# Commit your changes
git add .
git commit -m "Add amazing feature"

# Keep your branch updated
git fetch upstream
git rebase upstream/main

# Push to your fork
git push origin feature/amazing-feature
```

---

## Coding Standards

### PHP Code Style

SAGE follows PSR-12 coding standards with some project-specific conventions.

**Key Guidelines:**
- Use 4 spaces for indentation (no tabs)
- Opening braces on the same line for functions/methods
- Always use type hints for parameters and return types
- Document complex logic with clear comments
- Use meaningful variable and function names

**Example:**

```php
<?php

namespace Sage\Service;

use Sage\Model\Frame;

class FrameProcessor
{
    /**
     * Process a frame through the AI pipeline.
     *
     * @param Frame $frame The frame to process
     * @param array $options Processing options
     * @return Frame The processed frame
     */
    public function process(Frame $frame, array $options = []): Frame
    {
        // Validate input
        if (!$frame->isValid()) {
            throw new \InvalidArgumentException('Invalid frame provided');
        }

        // Process the frame
        $result = $this->applyFilters($frame, $options);
        
        return $result;
    }
}
```

### JavaScript Code Style

- Use ES6+ syntax where possible
- Use `const` and `let`, avoid `var`
- Use semicolons
- Use meaningful variable names
- Comment complex logic

### Database Conventions

- Table names: lowercase with underscores (e.g., `ai_frames`)
- Column names: lowercase with underscores (e.g., `created_at`)
- Use migrations for schema changes
- Always include proper indexes

---

## Commit Guidelines

We follow the Conventional Commits specification for clear commit history.

### Commit Message Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- `feat`: A new feature
- `fix`: A bug fix
- `docs`: Documentation only changes
- `style`: Code style changes (formatting, semicolons, etc.)
- `refactor`: Code change that neither fixes a bug nor adds a feature
- `perf`: Performance improvements
- `test`: Adding or updating tests
- `chore`: Changes to build process or auxiliary tools

### Examples

```bash
feat(gallery): add 3D model viewer support

Implemented Three.js integration for viewing 3D models in the gallery.
Includes rotation, zoom, and lighting controls.

Closes #123

---

fix(scheduler): prevent duplicate job execution

Added mutex locking to prevent race conditions when multiple
scheduler instances are running.

Fixes #456

---

docs(readme): update installation instructions for Termux

Added troubleshooting section and clarified MariaDB setup steps.
```

---

## Pull Request Process

### Before Submitting

1. **Update your branch** with the latest changes from `main`
2. **Test your changes** thoroughly
3. **Update documentation** if you've changed functionality
4. **Add tests** for new features (if testing framework exists)
5. **Follow the code style** guidelines

### PR Checklist

- [ ] Code follows the project's style guidelines
- [ ] Self-review of code completed
- [ ] Comments added for complex logic
- [ ] Documentation updated (if applicable)
- [ ] No new warnings generated
- [ ] Tests added/updated (if applicable)
- [ ] All tests passing
- [ ] Commit messages follow guidelines

### PR Template

When you open a PR, please include:

```markdown
## Description
Brief description of what this PR does.

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update

## Related Issues
Fixes #(issue number)

## How Has This Been Tested?
Describe the tests you ran and your testing environment.

## Screenshots (if applicable)
Add screenshots to demonstrate changes.

## Additional Notes
Any additional information reviewers should know.
```

### Review Process

1. At least one maintainer must approve the PR
2. All automated checks must pass
3. The PR must be up-to-date with the main branch
4. Maintainers may request changes or improvements
5. Once approved, a maintainer will merge the PR

---

## Testing

### Running Tests

```bash
# Run all tests
php bin/console test:run

# Run specific test suite
php bin/console test:run --suite=unit

# Run with coverage
php bin/console test:coverage
```

### Writing Tests

When adding new features, please include tests:

```php
<?php

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Sage\Service\FrameProcessor;

class FrameProcessorTest extends TestCase
{
    public function testProcessValidFrame(): void
    {
        $processor = new FrameProcessor();
        $frame = $this->createMockFrame();
        
        $result = $processor->process($frame);
        
        $this->assertInstanceOf(Frame::class, $result);
        $this->assertTrue($result->isProcessed());
    }
}
```

---

## Documentation

### Types of Documentation

- **Code Comments**: Explain complex logic
- **API Documentation**: Document public methods and classes
- **User Guides**: Help users accomplish tasks
- **Developer Guides**: Help contributors understand architecture

### Documentation Style

- Write in clear, concise English
- Use examples where possible
- Keep it up-to-date with code changes
- Structure with clear headings

---

## Community

### Communication Channels

- **GitHub Issues**: Bug reports and feature requests
- **GitHub Discussions**: General questions and ideas
- **Pull Requests**: Code contributions and reviews

### Getting Help

If you need help with your contribution:

1. Check existing documentation
2. Search closed issues and PRs
3. Ask in GitHub Discussions
4. Reach out to maintainers

---

## Recognition

Contributors will be recognized in:
- The project's README.md
- Release notes
- Our contributors page (if/when created)

---

## License

By contributing to SAGE, you agree that your contributions will be licensed under the MIT License.

---

## Questions?

Don't hesitate to ask! We're here to help make your contribution experience as smooth as possible.

Thank you for contributing to SAGE! 🎨✨

---

**© 2025 Sebastian Peter Wolbring (Peter Sebring)**