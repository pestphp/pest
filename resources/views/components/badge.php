<?php

/** @var string $type */
/** @var string $content */
[$bgBadgeColor, $bgBadgeText] = match ($type) {
    'INFO' => ['blue', 'INFO'],
    'ERROR' => ['red', 'ERROR'],
};

?>

<div class="my-1">
    <span class="ml-2 px-1 bg-<?php echo $bgBadgeColor ?> font-bold"><?php echo htmlspecialchars($bgBadgeText) ?></span>
    <span class="ml-1">
        <?php echo htmlspecialchars($content) ?>
    </span>
</div>
