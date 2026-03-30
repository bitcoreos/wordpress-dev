<?php
/**
 * Template Name: Artifact Pricing
 * Description: Subscription plans for BITCORE Artifact Hub
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #0a0a0f 0%, #1a1a2e 50%, #0f0f1a 100%);
            color: #e0e0e0;
            min-height: 100vh;
            line-height: 1.6;
        }
        .cosmic-bg {
            position: relative;
            min-height: 100vh;
        }
        .cosmic-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="0.5" fill="%23ffffff" opacity="0.3"/><circle cx="30" cy="25" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="50" cy="15" r="0.4" fill="%23ffffff" opacity="0.25"/><circle cx="70" cy="35" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="90" cy="20" r="0.5" fill="%23ffffff" opacity="0.3"/><circle cx="20" cy="50" r="0.4" fill="%23ffffff" opacity="0.2"/><circle cx="40" cy="70" r="0.3" fill="%23ffffff" opacity="0.25"/><circle cx="60" cy="60" r="0.5" fill="%23ffffff" opacity="0.3"/><circle cx="80" cy="80" r="0.3" fill="%23ffffff" opacity="0.2"/><circle cx="15" cy="85" r="0.4" fill="%23ffffff" opacity="0.25"/></svg>');
            background-size: 200px 200px;
            opacity: 0.5;
            pointer-events: none;
        }
        .pricing-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
        }
        .header-section {
            text-align: center;
            padding: 40px 20px;
            margin-bottom: 48px;
        }
        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 300;
            color: #fff;
            margin-bottom: 12px;
        }
        .header-section .subtitle {
            font-size: 1.1rem;
            color: #888;
        }
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 48px;
        }
        .pricing-card {
            background: rgba(17, 17, 24, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 32px;
            transition: all 0.3s;
            position: relative;
        }
        .pricing-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.3);
        }
        .pricing-card.free {
            background: rgba(17, 17, 24, 0.8);
        }
        .pricing-card.starter {
            background: linear-gradient(180deg, rgba(251, 191, 36, 0.1) 0%, rgba(17, 17, 24, 0.9) 100%);
            border-color: rgba(251, 191, 36, 0.3);
        }
        .pricing-card.pro {
            background: linear-gradient(180deg, rgba(99, 102, 241, 0.15) 0%, rgba(17, 17, 24, 0.9) 100%);
            border-color: rgba(99, 102, 241, 0.4);
        }
        .coming-soon-badge {
            position: absolute;
            top: -12px;
            right: 20px;
            padding: 6px 14px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #000;
            border-radius: 20px;
        }
        .tier-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
        }
        .tier-price {
            font-size: 3rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
        }
        .tier-price span {
            font-size: 1rem;
            font-weight: 400;
            color: #888;
        }
        .tier-desc {
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 24px;
        }
        .feature-list {
            list-style: none;
            margin-bottom: 32px;
        }
        .feature-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            font-size: 0.95rem;
            color: #e0e0e0;
        }
        .feature-list li::before {
            content: '✓';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #000;
            background: #4ade80;
            border-radius: 50%;
        }
        .feature-list li.disabled {
            color: #666;
        }
        .feature-list li.disabled::before {
            content: '×';
            background: #333;
            color: #666;
        }
        .cta-btn {
            display: block;
            width: 100%;
            padding: 16px;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        .cta-btn.primary {
            color: #000;
            background: linear-gradient(135deg, #4ade80, #22c55e);
        }
        .cta-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(74, 222, 128, 0.3);
        }
        .cta-btn.secondary {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .cta-btn.secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .cta-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .all-plans-section {
            background: rgba(17, 17, 24, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 32px;
        }
        .all-plans-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 20px;
            text-align: center;
        }
        .included-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        .included-feature {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #888;
        }
        .included-feature::before {
            content: '✓';
            color: #4ade80;
            font-weight: 700;
        }
        @media (max-width: 768px) {
            .header-section h1 { font-size: 1.75rem; }
            .pricing-grid { grid-template-columns: 1fr; }
            .tier-price { font-size: 2.5rem; }
        }
    </style>
</head>
<body class="cosmic-bg">
    <?php include(get_template_directory() . "/nav-header.php"); ?>
    <div class="pricing-container">
        <section class="header-section">
            <h1>Choose Your Plan</h1>
            <p class="subtitle">Unlock the full potential of AI artifacts</p>
        </section>

        <div class="pricing-grid">
            <div class="pricing-card free">
                <div class="tier-name">Free</div>
                <div class="tier-price">$0<span>/month</span></div>
                <p class="tier-desc">Perfect for exploring the platform</p>
                <ul class="feature-list">
                    <li>Access to free artifacts</li>
                    <li>Create up to 5 artifacts</li>
                    <li>Basic search & filters</li>
                    <li>Community support</li>
                    <li>1 artifact export/day</li>
                </ul>
                <button class="cta-btn primary">Get Started Free</button>
            </div>

            <div class="pricing-card starter">
                <div class="coming-soon-badge">Coming Soon</div>
                <div class="tier-name">Starter</div>
                <div class="tier-price">$5<span>/month</span></div>
                <p class="tier-desc">For power users and creators</p>
                <ul class="feature-list">
                    <li>Everything in Free</li>
                    <li>Create unlimited artifacts</li>
                    <li>Sell on marketplace</li>
                    <li>Priority support</li>
                    <li>Advanced analytics</li>
                    <li class="disabled">API access</li>
                </ul>
                <button class="cta-btn secondary disabled">Coming Soon</button>
            </div>

            <div class="pricing-card pro">
                <div class="coming-soon-badge">Coming Soon</div>
                <div class="tier-name">Pro</div>
                <div class="tier-price">$12<span>/month</span></div>
                <p class="tier-desc">For teams and professionals</p>
                <ul class="feature-list">
                    <li>Everything in Starter</li>
                    <li>Team collaboration</li>
                    <li>API access</li>
                    <li>Custom integrations</li>
                    <li>Private artifacts</li>
                    <li>Dedicated support</li>
                </ul>
                <button class="cta-btn secondary disabled">Coming Soon</button>
            </div>
        </div>

        <div class="all-plans-section">
            <h3 class="all-plans-title">All plans include</h3>
            <div class="included-features">
                <div class="included-feature">Access to artifact builder</div>
                <div class="included-feature">Version history</div>
                <div class="included-feature">Quality scoring</div>
                <div class="included-feature">Community marketplace access</div>
                <div class="included-feature">Basic analytics</div>
                <div class="included-feature">Export to multiple formats</div>
            </div>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
