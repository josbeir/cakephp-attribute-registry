<?php
/**
 * Single attribute item element for DebugKit panel
 *
 * @var \AttributeRegistry\ValueObject\AttributeInfo $attr
 * @var bool $showFile
 */

$showFile ??= false;
$targetType = $attr->target->type->value;
?>
<div class="attribute-item" data-attribute="<?= h($attr->attributeName) ?>" data-class="<?= h($attr->className) ?>">
    <div>
        <span class="target-type target-type-<?= h($targetType) ?>"><?= h($targetType) ?></span>
        <strong><?= h($attr->attributeName) ?></strong>
        on <code><?= h($attr->className) ?><?= $attr->target->targetName ? '::' . h($attr->target->targetName) : '' ?></code>
    </div>
    <?php if ($showFile): ?>
        <div class="file-path"><?= h($attr->filePath) ?>:<?= $attr->lineNumber ?></div>
    <?php else: ?>
        <div class="file-path">Line <?= $attr->lineNumber ?></div>
    <?php endif; ?>
    <?php if (!empty($attr->arguments)): ?>
        <div class="arguments">
            <?php foreach ($attr->arguments as $key => $value): ?>
                <div><strong><?= h($key) ?>:</strong> <?= h(is_string($value) ? $value : json_encode($value)) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
