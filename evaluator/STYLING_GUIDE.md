# Evaluator Panel Styling Guide

This guide explains how to apply consistent styling to evaluator panel pages using the shared CSS file.

## Quick Start

### 1. Include the CSS File
Add the evaluator stylesheet in the `<head>` section:
```php
<link href="css/evaluator-style.css" rel="stylesheet">
```

### 2. Wrap Content
Wrap your main content in the `.evaluator-content` div:
```php
<div class="evaluator-content">
    <div class="container-fluid">
        <!-- Your content here -->
    </div>
</div>
```

### 3. Use Page Header
```php
<div class="page-header">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-tasks me-2"></i>Page Title</h1>
            <p>Page description</p>
        </div>
    </div>
</div>
```

### 4. Use Dashboard Cards
```php
<div class="dashboard-card">
    <h5><i class="fas fa-icon me-2"></i>Section Title</h5>
    <!-- Card content -->
</div>
```

## Design System

### Colors
- **Primary**: #2563eb (blue)
- **Success**: #10b981 (green)
- **Warning**: #f59e0b (orange)
- **Danger**: #ef4444 (red)
- **Text Dark**: #1f2937
- **Text Muted**: #6b7280
- **Border**: #e5e7eb

### Components

#### Tables
```php
<table class="table">
    <thead>
        <tr>
            <th>Column 1</th>
            <th>Column 2</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Data 1</td>
            <td>Data 2</td>
        </tr>
    </tbody>
</table>
```

#### Badges
```php
<span class="badge bg-success">Success</span>
<span class="badge bg-warning">Warning</span>
<span class="badge bg-danger">Danger</span>
<span class="badge bg-info">Info</span>
<span class="badge bg-primary">Primary</span>
```

#### Buttons
```php
<!-- Primary buttons -->
<button class="btn btn-primary">Primary</button>
<button class="btn btn-success">Success</button>
<button class="btn btn-warning">Warning</button>
<button class="btn btn-danger">Danger</button>

<!-- Outline buttons -->
<button class="btn btn-outline-primary">Outline</button>
<button class="btn btn-outline-success">Success</button>

<!-- Button groups -->
<div class="btn-group">
    <button class="btn btn-primary btn-sm">Action 1</button>
    <button class="btn btn-outline-secondary btn-sm">Action 2</button>
</div>
```

#### Assignment Cards
```php
<div class="assignment-card">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h6 class="mb-2">Assignment Title</h6>
            <p class="mb-0 text-muted">Details</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary btn-sm">Action</button>
        </div>
    </div>
</div>
```

#### Stats Boxes
```php
<div class="card-stats">
    <div class="stat-box">
        <h4>42</h4>
        <small>Stat Label</small>
    </div>
    <div class="stat-box">
        <h4>18</h4>
        <small>Another Stat</small>
    </div>
</div>
```

## Design Principles

1. **Minimal Design**: Clean white backgrounds, subtle borders, no gradients
2. **Flat Colors**: Use CSS variables for consistent colors
3. **Simple Hover Effects**: Subtle shadows on hover, no transforms
4. **Professional Typography**: System fonts, clear hierarchy
5. **Responsive**: Mobile-friendly grid layouts
6. **Accessible**: Good contrast ratios, keyboard navigation

## Example Page Structure

```php
<?php
session_start();
// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'evaluator') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';
require_once '../includes/functions.php';

// Your PHP logic here

include '../includes/header.php';
?>

<link href="css/evaluator-style.css" rel="stylesheet">

<div class="evaluator-content">
<div class="container-fluid">
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-icon me-2"></i>Page Title</h1>
                <p>Page description</p>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="row">
        <div class="col-12">
            <div class="dashboard-card">
                <h5>Section Title</h5>
                
                <table class="table">
                    <!-- Table content -->
                </table>
            </div>
        </div>
    </div>
    
</div>
</div>

<?php include '../includes/footer.php'; ?>
```

## Migration Checklist

When converting a page to use the new styling:

- [ ] Add CSS link: `<link href="css/evaluator-style.css" rel="stylesheet">`
- [ ] Wrap content in `.evaluator-content` div
- [ ] Replace inline styles with CSS classes
- [ ] Convert page header to `.page-header` component
- [ ] Use `.dashboard-card` for content sections
- [ ] Replace gradient badges with flat `bg-*` classes
- [ ] Update buttons to use `.btn` variants
- [ ] Convert cards to `.assignment-card` where appropriate
- [ ] Use `.table` for tabular data
- [ ] Test responsiveness on mobile devices

## Color Reference

```css
/* Available through CSS variables */
var(--primary-color)   /* #2563eb - blue */
var(--success-color)   /* #10b981 - green */
var(--warning-color)   /* #f59e0b - orange */
var(--danger-color)    /* #ef4444 - red */
var(--text-dark)       /* #1f2937 - dark gray */
var(--text-muted)      /* #6b7280 - medium gray */
var(--border-color)    /* #e5e7eb - light gray */
var(--bg-light)        /* #f9fafb - off-white */
```

## Notes

- Authentication checks must happen **before** including the header to prevent white screen issues
- Close all `.evaluator-content` divs at the end of the page
- Use responsive Bootstrap grid for layouts
- Test on multiple screen sizes
- Keep content within `.container` or `.container-fluid`
