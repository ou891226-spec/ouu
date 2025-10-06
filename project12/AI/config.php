<?php
/**
 * AI 服務配置文件
 */

// 請在這裡設置您的 Gemini API 金鑰
define('GEMINI_API_KEY', 'AIzaSyCyYrIHY4RhM__FlGDZBPJS2XlL-83YoWo');

// 檢查API金鑰是否設置
if (empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'YOUR_ACTUAL_GEMINI_API_KEY_HERE') {
    die('請在 config.php 中設置您的實際 GEMINI_API_KEY');
}
?>
