# Athlete Training Platform - Software Stack

## Overview
This is an athlete training management application built with Laravel and Filament, focusing on exercise management, equipment tracking, and muscle group categorization.

## Core Framework & Runtime

### Backend
- **Laravel Framework**: v12.37.0 (Latest Laravel 12)
- **PHP**: v8.4.10 (requires ^8.2)
- **Livewire**: For reactive components

### Frontend
- **Alpine.js**: v3.15.0 - Lightweight JavaScript framework
- **Vite**: v7.0.4 - Build tool and dev server
- **Tailwind CSS**: v4.1.17 - Utility-first CSS framework

## Admin Panel & UI

### Filament (Admin Panel)
- **Filament**: v4.0 - Laravel admin panel framework
- **Filament Badgeable Column**: v3.0 (awcodes/filament-badgeable-column)
- **Filament Sticky Header**: v3.0 (awcodes/filament-sticky-header)
- **Filament Media Library Plugin**: v4.2 (Spatie integration)

### Icons & Styling
- **Blade Lucide Icons**: v1.23 (mallardduck/blade-lucide-icons)
- **Tailwind Typography**: v0.5.19
- **Autoprefixer**: v10.4.20

## Key Laravel Packages

### Media & File Management
- **Spatie Laravel Media Library**: v11.17 - Handle media files and uploads
- **Spatie Laravel Media Library Plugin**: v4.2 (Filament integration)

### Data Management
- **Spatie Laravel Data**: v4.18 - Data transfer objects
- **Spatie Laravel Schemaless Attributes**: v2.5 - Store unstructured data

### Database & Models
- **Tightenco Parental**: v1.4 - Single table inheritance for Eloquent

## Development Workflow

### Setup Command
```bash
composer setup
```
This runs: install dependencies, copy .env, generate key, migrate, install npm packages, and build assets

### Development Server
```bash
composer dev
```
Runs concurrently:
- PHP Artisan serve (server)
- Queue listener
- Laravel Pail (logs)
- Vite dev server

### Testing
```bash
composer test
```
Runs Pest PHP test suite

## Project Structure

This appears to be an exercise/fitness management application with:
- Exercise management (exercises, equipment, muscles)
- Filament resources for admin panel
- Media library integration for exercise images/videos
- Custom import commands for exercise data

## Key Features

- Admin panel built with Filament 4
- Exercise database with equipment and muscle tracking
- Media management for exercise demonstrations
- Reactive UI components with Livewire
- Modern build pipeline with Vite
- Comprehensive testing setup with Pest

## License
MIT
