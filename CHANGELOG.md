# Changelog

All notable changes to this project are documented in this file.

## [Unreleased] - 1.9.0

### Changed
- Require PHP `^8.2`, Sulu `^2.6` and Symfony `^6.4 | ^7.0`.
- Test suite migrated to PHPUnit 10.5 (`phpunit.xml.dist` uses the PHPUnit 10 schema with `<source>`); Prophecy is used via `phpspec/prophecy-phpunit`.
- Entity unit tests no longer boot the Sulu test kernel (`TestCase` instead of `SuluTestCase`).
- `.php-cs-fixer.php` enforces `declare(strict_types=1)` explicitly (newer `@Symfony:risky` rule sets would remove it); code base reformatted.

### Added
- GitHub Actions workflow `ci.yml`: PHP 8.2/8.3/8.4 matrix with `composer validate`, php-cs-fixer (dry run), PHPStan and PHPUnit.
- PHPStan (level 6) with `phpstan.neon.dist` and `phpstan-baseline.neon`.
- Composer scripts `phpstan`, `cs` and `cs-fix`.

### Removed
- Workflows `php.yml` and `symfony.yml` (broken `.env.test` copy step and `composer.lock` cache key).
- Dev dependencies `symfony/phpunit-bridge` and `phpspec/prophecy` (replaced by `phpspec/prophecy-phpunit`).
