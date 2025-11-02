# Student Panel Styling Guide

This guide explains how to apply consistent styling to student panel pages using the shared CSS file.

## Quick Start

### 1. Include the CSS File
Add the student stylesheet after the header:
```php
<?php
$pageTitle = "Page Title";
require_once('../includes/header.php');
?>

<link href="css/student-style.css" rel="stylesheet">
```

### 2. Wrap Content
Wrap your main content in the `.student-content` div:
```php
<div class="student-content">
<div class="container">
    <!-- Your content here -->
</div>
</div>
```

### 3. Use Page Header
```php
<div class="page-header">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-icon me-2"></i>Page Title</h1>
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
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Data 1</td>
            <td>Data 2</td>
            <td>
                <div class="btn-group">
                    <a href="#" class="btn btn-sm btn-primary">View</a>
                    <a href="#" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
            </td>
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

#### Submission Cards
```php
<div class="submission-card">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h6 class="mb-2">Submission Title</h6>
            <p class="mb-0 text-muted small">Details</p>
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
        <small>Total Submissions</small>
    </div>
    <div class="stat-box">
        <h4>18</h4>
        <small>Evaluated</small>
    </div>
    <div class="stat-box">
        <h4>85%</h4>
        <small>Average Score</small>
    </div>
</div>
```

#### Progress Bars
```php
<div class="progress">
    <div class="progress-bar bg-success" style="width: 75%"></div>
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
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

$student_id = $_SESSION['user_id'];

// Your PHP logic here

$pageTitle = "Page Title";
require_once('../includes/header.php');
?>

<link href="css/student-style.css" rel="stylesheet">

<div class="student-content">
<div class="container">
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-icon me-2"></i>Page Title</h1>
                <p>Page description</p>
            </div>
        </div>
    </div>
    
    <!-- Stats Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card-stats">
                <div class="stat-box">
                    <h4>42</h4>
                    <small>Total Items</small>
                </div>
                <div class="stat-box">
                    <h4>18</h4>
                    <small>Completed</small>
                </div>
                <div class="stat-box">
                    <h4>24</h4>
                    <small>Pending</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="row">
        <div class="col-12">
            <div class="dashboard-card">
                <h5><i class="fas fa-list me-2"></i>Section Title</h5>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Column 1</th>
                            <th>Column 2</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Table rows -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
</div>
</div>

<?php require_once('../includes/footer.php'); ?>
```

## Migration Checklist

When converting a page to use the new styling:

- [ ] Add CSS link: `<link href="css/student-style.css" rel="stylesheet">`
- [ ] Wrap content in `.student-content` and `.container` divs
- [ ] Replace inline styles with CSS classes
- [ ] Convert page header to `.page-header` component
- [ ] Use `.dashboard-card` for content sections
- [ ] Replace gradient badges with flat `bg-*` classes
- [ ] Update buttons to use `.btn` variants
- [ ] Convert cards to `.submission-card` where appropriate
- [ ] Use `.table` for tabular data
- [ ] Replace shadow-heavy cards with minimal borders
- [ ] Use `.card-stats` and `.stat-box` for statistics
- [ ] Test responsiveness on mobile devices
- [ ] Close all `.student-content` divs at the end

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

## Common Replacements

### Old Style → New Style

```php
<!-- Replace this: -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">Title</h5>
    </div>
    <div class="card-body">
        Content
    </div>
</div>

<!-- With this: -->
<div class="dashboard-card">
    <h5>Title</h5>
    Content
</div>

<!-- Replace this: -->
<span class="badge bg-success text-white">Success</span>

<!-- With this: -->
<span class="badge bg-success">Success</span>

<!-- Replace this: -->
<button class="btn btn-primary btn-lg">Action</button>

<!-- With this: -->
<button class="btn btn-primary">Action</button>
```

## Notes

- Authentication checks must happen **before** including the header
- Close all `.student-content` divs at the end of the page before footer
- Use responsive Bootstrap grid for layouts
- Test on multiple screen sizes
- Keep content within `.container` or `.container-fluid`
- Remove Bootstrap shadow classes (shadow-sm, shadow-lg)
- Remove rounded-pill for badges, use default rounded corners
- Simplify button sizes - use default or btn-sm
