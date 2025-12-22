<?php
/**
 * Attribute Registry DebugKit Panel Template
 *
 * @var array<\AttributeRegistry\ValueObject\AttributeInfo> $attributes
 * @var int $count
 * @var array<string, array<\AttributeRegistry\ValueObject\AttributeInfo>> $groupedByAttribute
 * @var array<string, array<\AttributeRegistry\ValueObject\AttributeInfo>> $groupedByTarget
 * @var array<string, mixed> $config
 */

use Cake\Utility\Text;

?>
<style>
.attribute-registry-panel {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.attribute-registry-panel .stats {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 4px;
}
.attribute-registry-panel .stat-item {
    text-align: center;
}
.attribute-registry-panel .stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}
.attribute-registry-panel .stat-label {
    font-size: 12px;
    color: #666;
}
.attribute-registry-panel .discover-btn {
    background: #4CAF50;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    margin-bottom: 20px;
}
.attribute-registry-panel .discover-btn:hover {
    background: #45a049;
}
.attribute-registry-panel .discover-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}
.attribute-registry-panel .tabs {
    display: flex;
    border-bottom: 2px solid #ddd;
    margin-bottom: 15px;
}
.attribute-registry-panel .tab {
    padding: 10px 20px;
    cursor: pointer;
    border: none;
    background: none;
    font-size: 14px;
    color: #666;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
}
.attribute-registry-panel .tab.active {
    color: #333;
    border-bottom-color: #4CAF50;
}
.attribute-registry-panel .tab-content {
    display: none;
}
.attribute-registry-panel .tab-content.active {
    display: block;
}
.attribute-registry-panel .group-header {
    background: #e9e9e9;
    padding: 8px 12px;
    margin-top: 15px;
    border-radius: 4px;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.attribute-registry-panel .group-header:hover {
    background: #ddd;
}
.attribute-registry-panel .group-count {
    background: #4CAF50;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
}
.attribute-registry-panel .group-items {
    border-left: 3px solid #4CAF50;
    margin-left: 10px;
    padding-left: 15px;
}
.attribute-registry-panel .attribute-item {
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}
.attribute-registry-panel .attribute-item:last-child {
    border-bottom: none;
}
.attribute-registry-panel .target-type {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}
.attribute-registry-panel .target-type-class { background: #e3f2fd; color: #1565c0; }
.attribute-registry-panel .target-type-method { background: #f3e5f5; color: #7b1fa2; }
.attribute-registry-panel .target-type-property { background: #e8f5e9; color: #2e7d32; }
.attribute-registry-panel .target-type-parameter { background: #fff3e0; color: #ef6c00; }
.attribute-registry-panel .target-type-constant { background: #fce4ec; color: #c2185b; }
.attribute-registry-panel .file-path {
    font-family: monospace;
    font-size: 12px;
    color: #666;
}
.attribute-registry-panel .arguments {
    margin-top: 5px;
    padding: 8px;
    background: #f9f9f9;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
}
.attribute-registry-panel .no-attributes {
    text-align: center;
    padding: 40px;
    color: #999;
}
.attribute-registry-panel .search-box {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 15px;
    font-size: 14px;
}
</style>

<div class="attribute-registry-panel">
    <div class="stats">
        <div class="stat-item">
            <div class="stat-value"><?= $count ?></div>
            <div class="stat-label">Total Attributes</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?= count($groupedByAttribute) ?></div>
            <div class="stat-label">Unique Types</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?= count($groupedByTarget) ?></div>
            <div class="stat-label">Files</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?= !empty($config['cache']['enabled']) ? 'Yes' : 'No' ?></div>
            <div class="stat-label">Cache Enabled</div>
        </div>
    </div>

    <button type="button" class="discover-btn" id="attribute-discover-btn" onclick="AttributeRegistryPanel.discover()">
        🔄 Re-Discover Attributes
    </button>

    <?php if ($count === 0): ?>
        <div class="no-attributes">
            <p>No attributes discovered.</p>
            <p>Configure paths in your <code>config/app_attribute_registry.php</code> file.</p>
        </div>
    <?php else: ?>
        <input type="text" class="search-box" id="attribute-search" placeholder="Search attributes..." onkeyup="AttributeRegistryPanel.filter(this.value)">

        <div class="tabs">
            <button type="button" class="tab active" data-tab="by-attribute">By Attribute</button>
            <button type="button" class="tab" data-tab="by-file">By File</button>
            <button type="button" class="tab" data-tab="all">All</button>
        </div>

        <div id="tab-by-attribute" class="tab-content active">
            <?php foreach ($groupedByAttribute as $attributeName => $items): ?>
                <div class="group-header" onclick="AttributeRegistryPanel.toggleGroup(this)">
                    <span><?= h($attributeName) ?></span>
                    <span class="group-count"><?= count($items) ?></span>
                </div>
                <div class="group-items">
                    <?php foreach ($items as $attr): ?>
                        <?= $this->element('AttributeRegistry.attribute_item', ['attr' => $attr]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="tab-by-file" class="tab-content">
            <?php foreach ($groupedByTarget as $file => $items): ?>
                <div class="group-header" onclick="AttributeRegistryPanel.toggleGroup(this)">
                    <span class="file-path"><?= h($file) ?></span>
                    <span class="group-count"><?= count($items) ?></span>
                </div>
                <div class="group-items">
                    <?php foreach ($items as $attr): ?>
                        <?= $this->element('AttributeRegistry.attribute_item', ['attr' => $attr]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="tab-all" class="tab-content">
            <?php foreach ($attributes as $attr): ?>
                <?= $this->element('AttributeRegistry.attribute_item', ['attr' => $attr, 'showFile' => true]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
window.AttributeRegistryPanel = {
    discover: function() {
        var btn = document.getElementById('attribute-discover-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Discovering...';

        fetch('/attribute-registry/debug-kit/discover', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = '✅ Found ' + data.count + ' attributes - Refresh to view';
            setTimeout(function() {
                btn.textContent = '🔄 Re-Discover Attributes';
            }, 3000);
        })
        .catch(function(error) {
            btn.disabled = false;
            btn.textContent = '❌ Error - Try again';
            console.error('Discover error:', error);
        });
    },

    toggleGroup: function(header) {
        var items = header.nextElementSibling;
        items.style.display = items.style.display === 'none' ? 'block' : 'none';
    },

    filter: function(query) {
        query = query.toLowerCase();
        var items = document.querySelectorAll('.attribute-registry-panel .attribute-item');
        items.forEach(function(item) {
            var text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? 'block' : 'none';
        });
    }
};

// Tab switching
document.querySelectorAll('.attribute-registry-panel .tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.attribute-registry-panel .tab').forEach(function(t) {
            t.classList.remove('active');
        });
        document.querySelectorAll('.attribute-registry-panel .tab-content').forEach(function(c) {
            c.classList.remove('active');
        });
        tab.classList.add('active');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
});
</script>
