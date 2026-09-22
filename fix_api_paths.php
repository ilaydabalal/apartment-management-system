<?php
$content = file_get_contents('frontend/dashboard.html');
$content = str_replace('/apartment-management/api', '/api', $content);

file_put_contents('frontend/dashboard.html', $content);
echo "✅ API yolları düzeltildi!\n";
echo "Artık tüm API çağrıları doğru yolu kullanacak.\n";
?> 