<?php
/*
|----------------------------------|
|==== Get Required Stripe Keys ====|
|----------------------------------|
*/

include('../wp-load.php');
include("sp-helpers.php");

class stripeKeys extends Sp_Helpers {
    
    public $wpdb;
    public $prefix;
    public $request;
    
    public function __construct($request) {
        global $wpdb;
        global $item;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix;
        $this->request = $request;
        $this->user_id = $this->request->logged_in_user_id;        
        if($this->user_id) {
            $this->prepare_item_for_response('requiredKeys');
        }
    }
    
    /*
    |--------------------------------|
    |===== My Schedule Settings =====|
    |--------------------------------|
    */
    
public function requiredKeys() {
    global $StripeMode, $backgroundCheckFee, $currency;

    ///// Stripe folder location main root directory
    include '../stripe/config.php';

    /// Get Stripe Details
    $spStripe = spStripeKey($this->user_id, $StripeMode); 

    // Step 1: Create an Ephemeral Key
    $ephemeralKey = \Stripe\EphemeralKey::create(
        ['customer' => $spStripe->stripe_id],
        ['stripe_version' => '2023-10-16'] // Use the latest Stripe API version
    );

    // Step 2: Create a SetupIntent for the Customer
    $setupIntent = \Stripe\SetupIntent::create([
        'customer' => $spStripe->stripe_id,
    ]);

    // Amount to be charged (background check fee)
    $amount = $backgroundCheckFee * 100; // Default $50 if not sent from app

    // Step 3: Create a PaymentIntent for the Customer
    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => $amount, // Ensure it's in cents
        'currency' => $currency,
        'automatic_payment_methods' => ['enabled' => true],
        'payment_method_types' => ['card', 'google_pay'], // Enable Google Pay and Card
    ]);

    // Prepare response with required data
    $response = array(
        'status' => 1,
        'message' => 'success',
        'customerId' => $spStripe->stripe_id,
        'paymentIntentId' => $paymentIntent->id,
        'paymentIntentClientSecret' => $paymentIntent->client_secret,
        'ephemeralKey' => $ephemeralKey->secret,
    );

    return $response;        
}

    
    /*
    |---------------------------------|
    |===== Prepare Json Response =====|
    |---------------------------------|
    */
    
    public function prepare_item_for_response($route){
        wp_send_json($this->$route());
    }
}

$json = file_get_contents('php://input');
new stripeKeys(json_decode($json));
