
<div class="wrap">
<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=argip')); ?>">
    <?php
    settings_fields('agrip_plugin');
    do_settings_sections('agrip_plugin');
    submit_button('Išsaugoti', 'primary', 'submit', true);
    ?>
</form>
</div>