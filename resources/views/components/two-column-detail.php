<div class="flex mx-2 max-w-150">
    <span>
        <?php echo htmlspecialchars($left) ?>
    </span>
    <span class="flex-1 content-repeat-[.] text-gray ml-1"></span>
    <?php if ($right !== '') { ?>
        <span class="ml-1 text-gray">
            <?php echo htmlspecialchars($right) ?>
        </span>
    <?php } ?>
</div>

