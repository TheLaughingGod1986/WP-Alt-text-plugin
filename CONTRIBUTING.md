# Contributing to SEO AI Alt Text Generator

Thank you for your interest in contributing! This document provides guidelines and instructions for contributing to the plugin.

## 🤝 How to Contribute

We welcome contributions of all kinds:
- 🐛 **Bug Reports** - Help us find and fix issues
- 💡 **Feature Requests** - Suggest new features
- 📝 **Documentation** - Improve docs and guides
- 🔧 **Code Contributions** - Submit pull requests
- 🌐 **Translations** - Help translate the plugin
- ⭐ **Reviews** - Leave reviews on WordPress.org

---

## 🚀 Getting Started

### Prerequisites

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Node.js (for asset building)
- Git

### Development Setup

1. **Clone the Repository**
   ```bash
   git clone https://github.com/benjaminoats/wp-alt-text-ai.git
   cd wp-alt-text-ai
   ```

2. **Set Up WordPress Environment**
   - Use Docker (see `docker-compose.yml`)
   - Or use your existing WordPress development environment

3. **Install Dependencies**
   ```bash
   npm install
   ```

4. **Start Local Development**
   ```bash
   ./start-local.sh  # If using Docker
   ```

5. **Enable Debug Mode**
   Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_LOCAL_DEV', true);
   ```

---

## 📋 Contribution Types

### 🐛 Reporting Bugs

**Before Reporting:**
- Check existing issues on GitHub
- Verify it's a plugin issue, not a WordPress/theme issue
- Test with default WordPress theme

**Bug Report Template:**
```markdown
**Description:**
Clear description of the bug

**Steps to Reproduce:**
1. Go to...
2. Click on...
3. See error...

**Expected Behavior:**
What should happen

**Actual Behavior:**
What actually happens

**Environment:**
- WordPress Version: X.X.X
- PHP Version: X.X.X
- Plugin Version: X.X.X
- Browser: Chrome/Firefox/etc.

**Screenshots:**
If applicable
```

### 💡 Feature Requests

**Feature Request Template:**
```markdown
**Feature Description:**
What feature would you like to see?

**Use Case:**
Why is this feature needed?

**Proposed Solution:**
How should it work?

**Alternatives Considered:**
Other approaches you've thought about
```

### 🔧 Code Contributions

#### Development Workflow

1. **Fork the Repository**
   - Create your fork on GitHub
   - Clone your fork locally

2. **Create a Branch**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/bug-description
   ```

3. **Make Changes**
   - Follow WordPress coding standards
   - Write clear, commented code
   - Update documentation as needed

4. **Test Your Changes**
   - Test on fresh WordPress install
   - Test on multiple PHP versions
   - Test on multiple browsers
   - Check for PHP syntax errors: `php -l filename.php`

5. **Commit Your Changes**
   ```bash
   git add .
   git commit -m "Description of changes"
   ```
   
   **Commit Message Guidelines:**
   - Use present tense: "Add feature" not "Added feature"
   - First line should be < 50 characters
   - Reference issue numbers: "Fix #123"
   - Examples:
     - `Fix: Resolve PHP syntax error in usage tracker`
     - `Add: New dashboard widget for usage stats`
     - `Update: Improve error messages for better UX`

6. **Push and Create Pull Request**
   ```bash
   git push origin feature/your-feature-name
   ```
   Then create a PR on GitHub

#### Coding Standards

**PHP:**
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use `phpcs` for code checking:
  ```bash
  composer install
  vendor/bin/phpcs --standard=WordPress includes/
  ```

**JavaScript:**
- Use ES5+ compatible code
- Follow WordPress JavaScript standards
- Use jQuery when needed (WordPress includes it)
- Wrap console.log in debug checks:
  ```javascript
  if (alttextaiDebug) console.log('Debug message');
  ```

**CSS:**
- Follow WordPress CSS standards
- Use CSS custom properties (variables)
- Mobile-first responsive design
- Use BEM-like naming conventions

#### Code Quality Checklist

Before submitting a PR:
- [ ] Code follows WordPress standards
- [ ] No PHP syntax errors
- [ ] No console.log in production code (use debug checks)
- [ ] Error logging respects WP_DEBUG
- [ ] All inputs sanitized
- [ ] All outputs escaped
- [ ] SQL queries use prepared statements
- [ ] Nonces on AJAX calls
- [ ] Tested on fresh WordPress install
- [ ] No hardcoded credentials or secrets
- [ ] Documentation updated if needed

---

## 📝 Documentation Contributions

### Types of Documentation

1. **Code Documentation**
   - PHPDoc comments for functions/classes
   - Inline comments for complex logic
   - `@since` tags for version tracking

2. **User Documentation**
   - README.md updates
   - FAQ additions
   - Usage examples

3. **Developer Documentation**
   - API documentation
   - Hook/filter documentation
   - Integration guides

### Documentation Standards

- Clear, concise writing
- Code examples where helpful
- Screenshots for UI changes
- Keep documentation up-to-date with code

---

## 🌐 Translation Contributions

### How to Translate

1. **Get Translation Files**
   - Language files are in `languages/`
   - POT file: `languages/seo-ai-alt-text-generator-auto-image-seo-accessibility.pot`

2. **Create Translation**
   - Use [Poedit](https://poedit.net/) or similar tool
   - Translate all strings
   - Test your translation

3. **Submit Translation**
   - Create PR with your `.po` and `.mo` files
   - Or submit via WordPress.org translation portal

---

## 🔍 Testing Your Contributions

### Manual Testing

1. **Basic Functionality**
   - Activate/deactivate plugin
   - Test all main features
   - Check for PHP errors in logs

2. **Browser Testing**
   - Test in Chrome, Firefox, Safari, Edge
   - Test mobile responsive design

3. **PHP Version Testing**
   - Test on PHP 7.4, 8.0, 8.1, 8.2

### Code Review Process

1. **Automated Checks**
   - GitHub Actions will run automated tests
   - Code style checks
   - PHP syntax validation

2. **Manual Review**
   - Maintainer reviews code
   - Feedback provided if needed
   - Changes requested if necessary

3. **Merge**
   - Once approved, PR is merged
   - Changes included in next release

---

## 📚 Project Structure

```
wp-alt-text-ai/
├── admin/              # Admin area functionality
├── assets/             # CSS, JS, images
├── includes/           # Core classes
├── languages/          # Translation files
├── public/             # Public-facing code
├── scripts/            # Development scripts
├── templates/          # PHP templates
├── ai-alt-gpt.php      # Main plugin file
└── readme.txt          # WordPress.org readme
```

### Key Files to Know

- `ai-alt-gpt.php` - Main plugin file
- `admin/class-ai-alt-gpt-core.php` - Core admin functionality
- `includes/class-api-client-v2.php` - API communication
- `includes/class-usage-event-tracker.php` - Usage tracking
- `assets/ai-alt-dashboard.js` - Frontend JavaScript
- `assets/ai-alt-dashboard.css` - Frontend CSS

---

## 🎯 Areas Needing Contribution

### High Priority
- **Testing** - Unit tests, integration tests
- **Documentation** - API docs, developer guides
- **Accessibility** - WCAG compliance improvements
- **Performance** - Optimization opportunities

### Medium Priority
- **New Features** - Feature requests from users
- **UI/UX Improvements** - Better user experience
- **Translations** - More language support
- **Browser Compatibility** - Edge cases

### Low Priority
- **Code Refactoring** - Code quality improvements
- **Documentation** - Enhanced guides
- **Examples** - Usage examples and tutorials

---

## 📞 Getting Help

### Resources
- **Documentation:** See `docs/` directory
- **GitHub Issues:** For bug reports and features
- **WordPress.org Forum:** For user support
- **Code Examples:** Check existing code

### Communication
- **Issues:** Use GitHub Issues
- **Discussions:** Use GitHub Discussions (if enabled)
- **Email:** For security issues (see below)

---

## 🔒 Security Issues

**Do NOT open public issues for security vulnerabilities.**

Instead, email security concerns to:
- **Email:** [Your security email]
- **Subject:** Security Issue - AltText AI Plugin

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

We take security seriously and will respond quickly.

---

## ✅ Code of Conduct

### Our Standards

- **Be Respectful** - Treat everyone with respect
- **Be Collaborative** - Work together constructively
- **Be Professional** - Maintain professional communication
- **Be Open** - Welcome newcomers and help them learn

### Unacceptable Behavior

- Harassment or discrimination
- Trolling or inflammatory comments
- Personal attacks
- Spam or off-topic discussions

---

## 🎁 Recognition

Contributors are recognized in:
- Plugin README (Contributors section)
- Release notes (when appropriate)
- WordPress.org plugin page (if submitted)

---

## 📄 License

By contributing, you agree that your contributions will be licensed under the same license as the project:
- **GPLv2 or later** - For all code
- Your contributions must also be GPLv2 compatible

---

## 🙏 Thank You!

Thank you for taking the time to contribute! Every contribution, no matter how small, makes the plugin better for everyone.

---

**Questions?** Open an issue or check the documentation in the `docs/` directory.

**Ready to contribute?** Fork the repo, make your changes, and submit a pull request!

🚀 **Let's make the web more accessible, one image at a time!**





