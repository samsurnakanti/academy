<?php
$dateFilterAction = $dateFilterAction ?? basename($_SERVER['PHP_SELF'] ?? '');
$dateFilterHidden = $dateFilterHidden ?? [];
?>
<form class="date-filter-form" method="get" action="<?= e($dateFilterAction) ?>">
    <?php foreach ($dateFilterHidden as $name => $value): ?>
        <input type="hidden" name="<?= e((string) $name) ?>" value="<?= e((string) $value) ?>">
    <?php endforeach; ?>
    <label>Range
        <select name="range" id="date-filter-range">
            <option value="all" <?= $dateFilter['range'] === 'all' ? 'selected' : '' ?>>All time</option>
            <option value="today" <?= $dateFilter['range'] === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="yesterday" <?= $dateFilter['range'] === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
            <option value="this_week" <?= $dateFilter['range'] === 'this_week' ? 'selected' : '' ?>>This week</option>
            <option value="this_month" <?= $dateFilter['range'] === 'this_month' ? 'selected' : '' ?>>This month</option>
            <option value="custom" <?= $dateFilter['range'] === 'custom' ? 'selected' : '' ?>>Custom dates</option>
        </select>
    </label>
    <label>From
        <input type="date" name="from" value="<?= e($dateFilter['from']) ?>">
    </label>
    <label>To
        <input type="date" name="to" value="<?= e($dateFilter['to']) ?>">
    </label>
    <button class="button small" type="submit">Apply</button>
    <a class="button small" href="<?= e($dateFilterAction) ?>">Clear</a>
</form>
