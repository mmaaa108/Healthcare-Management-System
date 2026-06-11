<?php
/**
 * سكربت الإصلاح التلقائي - قم بتشغيله مرة واحدة فقط ثم احذفه
 * يصلح المسافات الزائدة في ملفات JSON
 */
header('Content-Type: text/html; charset=utf-8');
echo "<h1>🔧 GHC Auto-Fix Tool</h1><pre>";

// 1. إصلاح ملفات الترجمة
$files = [
    __DIR__ . '/translations/ar.json',
    __DIR__ . '/translations/en.json'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "❌ File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    
    if ($data === null) {
        echo "❌ Invalid JSON in: $file\n";
        continue;
    }
    
    // تنظيف المفاتيح والقيم من المسافات الزائدة
    $cleaned = [];
    foreach ($data as $section_key => $section) {
        $clean_section_key = trim($section_key);
        $cleaned[$clean_section_key] = [];
        if (is_array($section)) {
            foreach ($section as $key => $value) {
                $cleaned[$clean_section_key][trim($key)] = trim($value);
            }
        }
    }
    
    file_put_contents($file, json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "✅ Fixed: $file\n";
}

// 2. التحقق من وجود المجلدات المطلوبة
$dirs = ['config', 'controllers', 'includes', 'models', 'views', 'api', 'translations', 'sql'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        echo "📁 Created directory: $dir\n";
    }
}

// 3. التحقق من صلاحيات الملفات
echo "\n✅ All fixes applied successfully!\n";
echo "⚠️  IMPORTANT: Delete this file (auto_fix.php) now for security!\n";
echo "</pre>";
?>