<?php
// One-time messages shown after a redirect (success / error)
function flash_set($type, $msg) { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
function flash_get() {
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
}

// Reads ?page= and ?per_page= safely
function get_page() { return max(1, (int)($_GET['page'] ?? 1)); }
function get_per_page() {
    $pp = (int)($_GET['per_page'] ?? 10);
    return in_array($pp, [5, 10, 25, 50], true) ? $pp : 10;   // whitelist
}

// Renders Bootstrap pagination. $params = current filters (q, per_page...) kept in links
function render_pagination($page, $pages, $params = []) {
    if ($pages <= 1) return '';
    $link = function ($p, $label, $disabled = false, $active = false) use ($params) {
        $url = '?' . http_build_query(array_merge($params, ['page' => $p]));
        $cls = 'page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '');
        return "<li class=\"$cls\"><a class=\"page-link\" href=\"" . e($url) . "\">$label</a></li>";
    };
    $html = '<nav><ul class="pagination pagination-sm flex-wrap mb-0">';
    $html .= $link($page - 1, '&laquo;', $page <= 1);
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
        $html .= $link($i, $i, false, $i === $page);
    }
    $html .= $link($page + 1, '&raquo;', $page >= $pages);
    return $html . '</ul></nav>';
}
