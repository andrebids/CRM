<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $this->load->view('authentication/includes/head.php'); ?>

<body>
    <div class="login-container">
        <!-- Lado esquerdo - Logo -->
        <div class="login-left">
    <div class="logo-wrapper">
        <img src="https://lightstudio.pt/assets/images/branco.svg" alt="Logo">
    </div>
</div>

        <!-- Lado direito - Formulário -->
        <div class="login-right">
            <div class="login-form-wrapper">
                <h1 class="tw-text-2xl tw-text-neutral-800 tw-font-semibold tw-mb-5">
                    <?php echo _l('admin_auth_login_heading'); ?>
                </h1>

                <?php $this->load->view('authentication/includes/alerts'); ?>
                <?php echo form_open($this->uri->uri_string()); ?>
                <?php echo validation_errors('<div class="alert alert-danger text-center">', '</div>'); ?>
                <?php hooks()->do_action('after_admin_login_form_start'); ?>

                <div class="form-group">
                    <label for="email" class="control-label">
                        <?php echo _l('admin_auth_login_email'); ?>
                    </label>
                    <input type="email" id="email" name="email" class="form-control" autofocus="1">
                </div>

                <div class="form-group">
                    <label for="password" class="control-label">
                        <?php echo _l('admin_auth_login_password'); ?>
                    </label>
                    <input type="password" id="password" name="password" class="form-control">
                </div>

                <?php if (show_recaptcha()) { ?>
                <div class="g-recaptcha tw-mb-4" data-sitekey="<?php echo get_option('recaptcha_site_key'); ?>"></div>
                <?php } ?>

                <div class="form-group">
                    <div class="checkbox checkbox-inline">
                        <input type="checkbox" value="estimate" id="remember" name="remember">
                        <label for="remember"> <?php echo _l('admin_auth_login_remember_me'); ?></label>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <?php echo _l('admin_auth_login_button'); ?>
                    </button>
                </div>

                <div class="form-group">
                    <a href="<?php echo admin_url('authentication/forgot_password'); ?>">
                        <?php echo _l('admin_auth_login_fp'); ?>
                    </a>
                </div>

                <?php hooks()->do_action('before_admin_login_form_close'); ?>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</body>

</html>