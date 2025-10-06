# Zoho SSO WordPress Plugin

Simple Zoho Single Sign-On integration for WordPress with subscription data support.

## Features

- Single Sign-On with Zoho accounts
- Automatic user creation on first login
- Syncs user profile data (name, email)
- Fetches and stores Zoho Subscriptions data
- Helper functions to check subscription status

## Installation

1. Download the plugin files
2. Create a folder `zoho-sso` in `/wp-content/plugins/`
3. Upload `zoho-sso.php` to the folder
4. Activate the plugin from WordPress Admin → Plugins

## Zoho OAuth Setup

### Step 1: Create OAuth Client

1. Go to [Zoho API Console](https://api-console.zoho.com/)
2. Click **Add Client**
3. Select **Server-based Applications**
4. Fill in the details:
   - **Client Name:** Your WordPress Site
   - **Homepage URL:** Your website URL
   - **Authorized Redirect URIs:** (You'll get this from WordPress settings)

### Step 2: Get Organization ID (for Subscriptions)

1. Go to [Zoho Subscriptions](https://subscriptions.zoho.com/)
2. Navigate to **Settings → Organization**
3. Copy your **Organization ID**

### Step 3: Configure Scopes

Make sure your OAuth client has these scopes enabled:
- `AaaServer.profile.READ`
- `ZohoSubscriptions.subscriptions.READ`

## WordPress Configuration

1. Go to **Settings → Zoho SSO** in WordPress admin
2. Enter your **Client ID** from Zoho
3. Enter your **Client Secret** from Zoho
4. Select your **Zoho Domain** (.com, .eu, .in, etc.)
5. Enter your **Organization ID** (optional, for subscriptions)
6. Copy the **Redirect URI** shown
7. Go back to Zoho API Console and add this Redirect URI to your OAuth client
8. Click **Save Changes**

## Usage

### Login Button

A "Sign in with Zoho" button automatically appears on the WordPress login page.

### Check Active Subscription

```php
// Check if current user has active subscription
$user_id = get_current_user_id();

if (Zoho_SSO::has_active_subscription($user_id)) {
    echo "You have an active subscription!";
} else {
    echo "No active subscription found.";
}
```

### Get Subscription Details

```php
// Get all subscription data
$user_id = get_current_user_id();
$details = Zoho_SSO::get_subscription_details($user_id);

echo "Customer ID: " . $details['customer_id'];
echo "Last Updated: " . $details['last_updated'];

// Loop through subscriptions
foreach ($details['subscriptions'] as $sub) {
    echo "Plan: " . $sub['plan']['name'];
    echo "Status: " . $sub['status'];
    echo "Amount: " . $sub['amount'];
    echo "Next Billing: " . $sub['next_billing_at'];
}
```

### Restrict Content by Subscription

```php
// In your theme template
if (!is_user_logged_in()) {
    echo "Please log in to view this content.";
    return;
}

if (!Zoho_SSO::has_active_subscription(get_current_user_id())) {
    echo "This content requires an active subscription.";
    return;
}

// Show premium content
echo "Welcome, premium member!";
```

### Access Raw User Meta

```php
$user_id = get_current_user_id();

// Get customer ID
$customer_id = get_user_meta($user_id, 'zoho_customer_id', true);

// Get all subscriptions
$subscriptions = get_user_meta($user_id, 'zoho_subscriptions', true);

// Get last sync time
$updated = get_user_meta($user_id, 'zoho_subscription_updated', true);
```

### Subscription Statuses

Common subscription statuses returned by Zoho:
- `live` - Active subscription
- `active` - Active subscription
- `non_renewing` - Active but will not renew
- `cancelled` - Cancelled subscription
- `expired` - Expired subscription
- `trial` - Trial period

## Sample Implementation

### Restrict Admin Access

```php
// Add to functions.php
add_action('admin_init', function() {
    if (!current_user_can('administrator')) {
        $user_id = get_current_user_id();
        
        if (!Zoho_SSO::has_active_subscription($user_id)) {
            wp_redirect(home_url());
            exit;
        }
    }
});
```

### Show Subscription Info in Profile

```php
// Add to functions.php
add_action('show_user_profile', 'show_zoho_subscription_info');
add_action('edit_user_profile', 'show_zoho_subscription_info');

function show_zoho_subscription_info($user) {
    $details = Zoho_SSO::get_subscription_details($user->ID);
    
    if (!$details['subscriptions']) {
        return;
    }
    
    echo '<h3>Zoho Subscription Info</h3>';
    echo '<table class="form-table">';
    
    foreach ($details['subscriptions'] as $sub) {
        echo '<tr>';
        echo '<th>Plan</th>';
        echo '<td>' . esc_html($sub['plan']['name']) . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th>Status</th>';
        echo '<td>' . esc_html($sub['status']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
}
```

### Custom Login Redirect

```php
// Redirect based on subscription status
add_filter('login_redirect', function($redirect_to, $request, $user) {
    if (isset($user->ID)) {
        if (Zoho_SSO::has_active_subscription($user->ID)) {
            return admin_url('index.php'); // Dashboard
        } else {
            return home_url('/subscribe'); // Subscribe page
        }
    }
    return $redirect_to;
}, 10, 3);
```

## Troubleshooting

### "Invalid Callback" Error
- Make sure the Redirect URI in WordPress settings matches exactly in Zoho OAuth settings
- Check that the redirect URI doesn't have trailing slashes

### "0" on Login Button Click
- Clear WordPress cache
- Deactivate and reactivate the plugin
- Check if pretty permalinks are enabled

### No Subscription Data
- Verify Organization ID is correct
- Ensure the OAuth scope includes `ZohoSubscriptions.subscriptions.READ`
- Check that the user's email exists as a customer in Zoho Subscriptions

### Users Can't Login
- Verify Client ID and Secret are correct
- Check that the Zoho domain setting matches your account (.com, .eu, etc.)
- Review Zoho API Console for any error logs

## Security Notes

- Users are auto-created on first login
- Random passwords are generated (users can't login with username/password)
- Client Secret is stored in WordPress options (keep your database secure)
- Subscription data is synced on each login

## Support

For Zoho OAuth issues, visit [Zoho API Documentation](https://www.zoho.com/accounts/protocol/oauth.html)

For Zoho Subscriptions API, visit [Zoho Subscriptions API Docs](https://www.zoho.com/subscriptions/api/v1/)

## License

This plugin is provided as-is for integration purposes.