<div class="north-auth-shell">
    <div class="north-auth-brand">
        <div class="north-auth-brand-icon">
            <i class="fas fa-warehouse"></i>
        </div>
        <div class="north-auth-brand-copy">
            <span class="north-auth-brand-title">NORTHLANDIA</span>
            <span class="north-auth-brand-subtitle">Inventory System</span>
        </div>
    </div>

    <div class="card north-auth-card">
        <div class="card-body">
            <h3 class="text-center">Welcome back</h3>
            <p class="text-center text-muted mb-4">Sign in to continue to your dashboard</p>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="north-auth-alert">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form class="north-auth-form" id="login-form" method="post" action="<?= BASE_URL ?>/Auth/login">
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input id="username" name="username" class="form-control" placeholder="Enter your username" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input id="password" type="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>
            </form>
        </div>
    </div>
</div>
