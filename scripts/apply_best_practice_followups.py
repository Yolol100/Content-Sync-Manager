#!/usr/bin/env python3
"""Apply WPCS-confirmed follow-up hardening after the 2026 baseline patch."""

from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, text: str) -> None:
    (ROOT / path).write_text(text, encoding="utf-8", newline="\n")


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 match, found {count}")
    return text.replace(old, new, 1)


manager = read("includes/manager.php")

manager = replace_once(
    manager,
    "function dca_tb_get_admin_taxonomy() {\n",
    "// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin screen routing; these values do not mutate state.\nfunction dca_tb_get_admin_taxonomy() {\n",
    "admin routing nonce annotation start",
)
manager = replace_once(
    manager,
    "    return 'post';\n}\n\nfunction dca_tb_post_type_label_single($post_id) {",
    "    return 'post';\n}\n// phpcs:enable WordPress.Security.NonceVerification.Recommended\n\nfunction dca_tb_post_type_label_single($post_id) {",
    "admin routing nonce annotation end",
)
manager = replace_once(
    manager,
    "function dca_tb_get_list_status_filter() {\n",
    "// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters; no state-changing action is performed.\nfunction dca_tb_get_list_status_filter() {\n",
    "list filter nonce annotation start",
)
manager = replace_once(
    manager,
    "    return in_array($template, ['standard'], true) ? $template : '';\n}\n\nfunction dca_tb_apply_standard_template_filter_where",
    "    return in_array($template, ['standard'], true) ? $template : '';\n}\n// phpcs:enable WordPress.Security.NonceVerification.Recommended\n\nfunction dca_tb_apply_standard_template_filter_where",
    "list filter nonce annotation end",
)
manager = replace_once(
    manager,
    "        dca_tb_update_badge($post_id),\n        dca_tb_content_badge($post_id)",
    "        wp_kses_post(dca_tb_update_badge($post_id)),\n        wp_kses_post(dca_tb_content_badge($post_id))",
    "badge output escaping",
)
manager = replace_once(
    manager,
    "function dca_tb_post_int($key) {\n",
    "// phpcs:disable WordPress.Security.NonceVerification.Missing -- Central POST accessors are consumed by AJAX handlers after dca_tb_require_ajax_access().\nfunction dca_tb_post_int($key) {\n",
    "central POST nonce annotation start",
)
manager = replace_once(
    manager,
    "    return (string) wp_unslash($_POST[$key]);\n}",
    "    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Full TXT/request text is intentionally unslashed here and sanitized/validated by the schema-specific caller.\n    return (string) wp_unslash($_POST[$key]);\n}",
    "raw text accessor annotation",
)
manager = replace_once(
    manager,
    "    return dca_tb_sanitize_post_id_list(wp_unslash($_POST[$key]));\n}\n\nfunction dca_tb_current_user_can_use_manager() {",
    "    return dca_tb_sanitize_post_id_list(wp_unslash($_POST[$key]));\n}\n// phpcs:enable WordPress.Security.NonceVerification.Missing\n\nfunction dca_tb_current_user_can_use_manager() {",
    "central POST nonce annotation end",
)
write("includes/manager.php", manager)

ai = read("includes/ai-image-context.php")
ai = replace_once(
    ai,
    "    $scope = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : 'content';\n    $raw_ids = isset($_POST['ids']) ? wp_unslash($_POST['ids']) : [];\n    if (!is_array($raw_ids)) {\n        wp_send_json_error(['message' => 'Ongeldige selectie.'], 400);\n    }\n\n    $ids = array_values(array_unique(array_filter(array_map('absint', $raw_ids))));",
    "    $scope = sanitize_key(dca_tb_post_text('scope'));\n    $scope = $scope !== '' ? $scope : 'content';\n    $ids = dca_tb_post_id_list('ids');",
    "AI export request helpers",
)
ai = ai.replace(
    "    $text = isset($_POST['text']) ? wp_unslash((string) $_POST['text']) : '';",
    "    $text = dca_tb_post_text('text');",
)
if "$_POST['text']" in ai or "$_POST['ids']" in ai or "$_POST['scope']" in ai:
    raise SystemExit("AI request refactor left direct request access behind")
write("includes/ai-image-context.php", ai)

print("Applied WPCS follow-up hardening.")
