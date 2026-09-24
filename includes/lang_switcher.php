<?php
/** Language switcher control */
$cur = current_lang();
?>
<div class="btn-group btn-group-sm wc-lang-switch" role="group" aria-label="<?= e(__('lang.switch')) ?>">
    <a href="<?= e(lang_switch_url('zh')) ?>"
       class="btn <?= $cur === 'zh' ? 'btn-light' : 'btn-outline-light' ?>"
       title="中文">中文</a>
    <a href="<?= e(lang_switch_url('en')) ?>"
       class="btn <?= $cur === 'en' ? 'btn-light' : 'btn-outline-light' ?>"
       title="English">EN</a>
</div>
