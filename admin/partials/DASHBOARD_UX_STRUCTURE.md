# Dashboard UX Structure - Component Tree

## Three-Layer UX Architecture

### Layer 1: Status + Plan
**Purpose**: Compact plan information, quota usage, renewal date
**Visual Weight**: Reduced (not dominating workflow)

**Components**:
- `PlanStatusCard` (compact)
  - Plan badge
  - Circular usage gauge (smaller: 140px)
  - Usage count (used / limit)
  - Renewal date
  - Plan description ("who this plan is for")
  - Billing link (if premium)

- `UpgradeCard` (compact, only if Free/Growth)
  - Upgrade badge
  - Title
  - Feature list
  - CTA button

---

### Layer 2: Progress + KPIs
**Purpose**: Coverage metric (hero), outcome-focused statistics
**Visual Weight**: Primary focus

**Components**:
- `CoverageCard` (hero metric)
  - Coverage percentage (large, prominent)
  - Progress bar
  - Images needing alt text count
  - Contextual CTA (optimise or upgrade)
  - Completion message (if 100%)

- `KPICards` (3-column grid)
  - `MetricCard: AI Alt Text Generated`
    - Value
    - Label
    - Empty state: Educational benefit copy
    - With data: Timeframe
  
  - `MetricCard: Images Optimised`
    - Value
    - Label
    - Empty state: Educational benefit copy
    - With data: "Total with alt text"
  
  - `MetricCard: Time Saved`
    - Value
    - Label
    - Description: Outcome-focused copy

---

### Layer 3: Actions + Workflow
**Purpose**: Clear first action, workflow entry points, queue status
**Visual Weight**: Action-oriented

**Components**:
- `EmptyStateBlock` (if library = 0)
  - Icon
  - Headline: "Scan your media library to begin"
  - Description: Educational copy
  - Primary CTA: "Scan Library"

- `TasksNeedingCompletion` (if library > 0 && unprocessed)
  - Warning-style card
  - Count of images needing alt text
  - Primary CTA: "Optimise Now"
  - Secondary CTA: "View in Library"

- `WorkflowActionsPanel`
  - Title: "Optimise Images"
  - Subtitle
  - `QueueStats` (3 stats)
    - In Queue
    - Run Today
    - Needs Attention
  - `ActionButtons` (hierarchy)
    - Primary: "Optimise New Images"
    - Secondary: "Bulk Optimise All" (blocked for Free with upgrade trigger)
    - Tertiary: "Open ALT Library"
  - `UpgradeBlocker` (conditional)
    - Shows when Free user attempts bulk on large library
    - Upgrade CTA

---

## Empty State Logic

### State 1: Library Size = 0
**Display**: `EmptyStateBlock`
- Message: "Scan your media library to begin"
- Educational copy about benefits
- Primary action: "Scan Library"

### State 2: Library > 0 but Unprocessed
**Display**: `TasksNeedingCompletion` + `WorkflowActionsPanel`
- Shows count of images needing alt text
- Primary action: "Optimise Now"
- Secondary: "View in Library"

### State 3: Stats = 0 (but library exists)
**Display**: Educational microcopy in metric cards
- Explains benefit, not instruction
- Outcome-focused language

---

## Upgrade Triggers (Contextual)

### Trigger 1: Bulk Action on Free Plan
**Condition**: Free user clicks "Bulk Optimise All"
**Action**: Show upgrade modal
**UI**: Badge indicator on button + upgrade blocker message

### Trigger 2: Coverage > 70% + Large Library
**Condition**: `coverage >= 70%` AND `total_images >= 50` AND `is_free`
**Action**: Show subtle "Finish in bulk via Growth" CTA
**UI**: Secondary button in coverage card

### Trigger 3: Large Library + Free Plan
**Condition**: `remaining_images > 10` AND `is_free`
**Action**: Show upgrade blocker message
**UI**: Warning-style message in workflow panel

### No Trigger When:
- Library size = 0
- No actionable items exist
- User already on premium plan

---

## Action Hierarchy

### Primary Actions
- "Scan Library" (if no library)
- "Optimise Now" (if images need alt text)
- "Optimise New Images" (workflow entry)

### Secondary Actions
- "Bulk Optimise All" (blocked for Free with upgrade trigger)
- "View in Library"
- "Optimise remaining" (in coverage card)

### Tertiary Actions
- "Open ALT Library" (link-style button)
- "Upgrade now" (text link in blocker)

---

## Data Flow

### Props/State Needed:
```php
// Plan & Usage
$plan_slug // 'free', 'growth', 'agency'
$usage_limit
$alt_texts_generated
$usage_percent
$reset_date

// Library Stats
$total_images
$optimized
$remaining_images
$alt_text_coverage // percentage

// Queue Stats
$in_queue
$run_today
$needs_attention

// Computed States
$library_scanned // $total_images > 0
$library_unprocessed // $remaining_images > 0
$has_no_library // $total_images === 0
$can_generate // has quota or premium
$should_show_bulk_upgrade // coverage >= 70% && large library && free
```

---

## Conditional Rendering Logic

```php
// Layer 1: Always show
if ($is_authenticated || $has_license || $has_registered_user)

// Layer 2: Coverage card
if ($library_scanned && $alt_text_coverage !== null)

// Layer 3: Empty state
if ($has_no_library)
  → Show EmptyStateBlock

else
  → if ($library_unprocessed)
    → Show TasksNeedingCompletion
  → Show WorkflowActionsPanel
  → if ($is_free && $remaining_images > 10)
    → Show UpgradeBlocker
```

---

## Copy Guidelines

### Educational (Empty States)
- Focus on outcomes: "improves SEO", "makes site accessible"
- Not instructions: Avoid "Click here to..."
- Benefit-focused: "Each alt text improves your SEO rankings"

### Action-Oriented (CTAs)
- Clear verbs: "Scan Library", "Optimise Now"
- Outcome hint: "Finish in bulk via Growth"
- Contextual: "Optimise remaining" (when coverage shown)

### Upgrade Messages
- Contextual: Only when relevant
- Value-focused: "process X images at once"
- Not aggressive: Subtle, helpful
