<?php
/**
 * Antifraud Dashboard
 *
 * Dashboard page for order details.
 *
 * @package WordPress
 */

$afp_args = array(
    'limit' => -1,
    'return' => 'ids',
);
    $afp_query = new WC_Order_Query($afp_args);
    $afp_orders = $afp_query->get_orders();
    $number_of_orders = 0;
    $total_transaction_amt = 0;
    $number_of_low_risk_orders = 0;
    $number_of_medium_risk_orders = 0;
    $number_of_high_risk_orders = 0;
foreach ($afp_orders as $order_id) {
    $number_of_orders++;
    $wc_af_score = intval(get_post_meta($order_id, 'wc_af_score', true));
    $total_transaction_amt += get_post_meta($order_id, '_order_total', true);
    $meta = WC_AF_Score_Helper::get_score_meta($wc_af_score);
    if ('Low Risk' == $meta['label']) {
        $number_of_low_risk_orders++;
    }
    if ('Medium Risk' == $meta['label']) {
        $number_of_medium_risk_orders++;
    }
    if ('High Risk' == $meta['label']) {
        $number_of_high_risk_orders++;
    }
}


    global $wpdb;
    $currency = get_woocommerce_currency();
    $currency = get_woocommerce_currency_symbol($currency);
    $date_from = gmdate('Y-m-d');
    $date_to = gmdate('Y-m-d', strtotime('-1 days'));

        $sql = "SELECT * FROM $wpdb->posts 
                WHERE post_type = 'shop_order'
                AND post_date BETWEEN '" . $date_to . "  00:00:00' AND '" . $date_from . " 23:59:59'";
    $result = $wpdb->get_results($sql);

    $wc_settings_anti_fraudblacklist_emails = get_option('wc_settings_anti_fraudblacklist_emails');
    $wc_settings_anti_fraudblacklist_emails = explode(',', $wc_settings_anti_fraudblacklist_emails);
    $total_orders = 0;
    $total_transaction_amt24 = 0;
    $high_risk_transaction_amt24 = 0;
    $number_of_low_risk_orders24 = 0;
    $number_of_medium_risk_orders24 = 0;
    $number_of_high_risk_orders24 = 0;
    $number_of_high_risk_orders_hold24 = 0;
    $number_of_high_risk_orders_cancelled24 = 0;
    $number_of_paypal_verification_orders = 0;
    $block_emails = array();
foreach ($result as $order) {
    $total_orders++;
    $email = get_post_meta($order->ID, '_billing_email', true);
    $total_transaction_amt24 += get_post_meta($order->ID, '_order_total', true);
    $order_currency = get_post_meta($order->ID, '_order_currency', true);
    $wc_af_score = intval(get_post_meta($order->ID, 'wc_af_score', true));
    $paypal_status = get_post_meta($order->ID, '_paypal_status', true);
    $meta = WC_AF_Score_Helper::get_score_meta($wc_af_score);
    if ('Low Risk' == $meta['label']) {
        $number_of_low_risk_orders24++;
    }
    if ('Medium Risk' == $meta['label']) {
        $number_of_medium_risk_orders24++;
    }
    if ('High Risk' == $meta['label'] && 'wc-on-hold' == $order->post_status) {
        $number_of_high_risk_orders_hold24++;
    }
    if ('High Risk' == $meta['label'] && 'wc-cancelled' == $order->post_status) {
        $number_of_high_risk_orders_cancelled24++;
    }
    if ('High Risk' == $meta['label']) {
        $high_risk_transaction_amt24 += get_post_meta($order->ID, '_order_total', true);
        $number_of_high_risk_orders24++;
    }
    if (in_array($email, $wc_settings_anti_fraudblacklist_emails)) {
        $block_emails[] = $email;
    }
    if (0 == $wc_af_score && 'wc-cancelled' == $order->post_status) {
        $high_risk_transaction_amt24 += get_post_meta($order->ID, '_order_total', true);
        $number_of_high_risk_orders_cancelled24++;
    }
    if ('pending' == $paypal_status) {
        $number_of_paypal_verification_orders++;
    }
}
    $block_emails = array_count_values($block_emails);


    $date_from = gmdate('Y-m-d');
    $date_to = gmdate('Y-m-d', strtotime('-6 days'));
    $last7_days = array();
for ($i = 6; $i > 0; $i--) {
    $last7_days[] = gmdate('d F', strtotime('-' . $i . ' days'));
}
    $last7_days[] = gmdate('d F');
    $result = $wpdb->get_results(
        "SELECT * FROM $wpdb->posts 
                WHERE post_type = 'shop_order'
                AND post_date BETWEEN '" . $date_to . "  00:00:00' AND '" . $date_from . " 23:59:59'
    "
    );

    $low_score_arr = array();
    $medium_score_arr = array();
    $high_score_arr = array();
    foreach ($result as $order) {
        $order_date = gmdate('d F', strtotime($order->post_date));

        foreach ($last7_days as $day) {
            if ($day == $order_date) {
                $wc_af_score = intval(get_post_meta($order->ID, 'wc_af_score', true));
                $meta = WC_AF_Score_Helper::get_score_meta($wc_af_score);
                if ('Low Risk' == $meta['label']) {
                    $low_score_arr[] = $order_date;
                }
                if ('Medium Risk' == $meta['label']) {
                    $medium_score_arr[] = $order_date;
                }
                if ('High Risk' == $meta['label']) {
                    $high_score_arr[] = $order_date;
                }
                if (0 == $wc_af_score) {
                    $high_score_arr[] = $order_date;
                }
            }
        }
    }

    $low_score_arr_val = array_count_values($low_score_arr);
    $medium_score_arr = array_count_values($medium_score_arr);
    $high_score_arr = array_count_values($high_score_arr);
    $low_week_arr = $medium_week_arr = $high_week_arr = array();
    foreach ($last7_days as $day) {
        if (array_key_exists($day, $low_score_arr_val)) {
            $low_week_arr[] = $low_score_arr_val[ $day ];
        } else {
            $low_week_arr[] = 0;
        }
        if (array_key_exists($day, $medium_score_arr)) {
            $medium_week_arr[] = $medium_score_arr[ $day ];
        } else {
            $medium_week_arr[] = 0;
        }
        if (array_key_exists($day, $high_score_arr)) {
            $high_week_arr[] = $high_score_arr[ $day ];
        } else {
            $high_week_arr[] = 0;
        }
    }
    ?>

<div class="dash-row">

<h1 style="text-align:center;font-size:50px;color:white;line-height: 1.2em;">Anti Fraud Dashboard</h1> 

</div>

<div class="dash-row">

    <div class="metric-box metric-style1">

    <img src="<?php echo esc_url(plugin_dir_url(__FILE__)) . 'icons/cart.svg'; ?>">   

        <h2><?php echo esc_attr($number_of_orders); ?></h2>Orders Detected</div> 

    <div class="metric-box metric-style2">

        <img src="<?php echo esc_url(plugin_dir_url(__FILE__)) . 'icons/low-risk.svg'; ?>">

        <h2><?php echo esc_attr($number_of_low_risk_orders); ?></h2>Low Risk</div>

    <div class="metric-box metric-style3">

        <img src="<?php echo esc_url(plugin_dir_url(__FILE__)) . 'icons/med-risk.svg'; ?>">

        <h2><?php echo esc_attr($number_of_medium_risk_orders); ?></h2>Medium Risk</div>

    <div class="metric-box metric-style4">

        <img src="<?php echo esc_url(plugin_dir_url(__FILE__)) . 'icons/high-risk.svg'; ?>">

        <h2><?php echo esc_attr($number_of_high_risk_orders); ?></h2>Needs Attention</div>

</div>

    

    

    <div class="dash-row">

    <div class="dash-section-50 bar-chart">

        <h2 style="color:white">Recent Order Data</h2>

        <div class="chart-wrapper">

<canvas id="bar-chart-grouped"></canvas>

</div>

    </div>

    

    <div class="dash-section-50 dash-stats">

        <h2 style="color:white;">Last 24 Hours Update</h2>

<div class="blurb">

    <div class="blurb-inner">

<img src="<?php echo plugin_dir_url(__FILE__) . 'icons/totaol.svg'; ?>"><h3>Total Transaction Amount</h3>

    <div class="blurb-content"><span><?php echo esc_attr($currency . $total_transaction_amt24); ?></span></div>

</div>

</div>

<div class="blurb">

    <div class="blurb-inner">

<img src="<?php echo plugin_dir_url(__FILE__) . 'icons/hash.svg'; ?>"><h3>Total Number of Orders</h3>

    <div class="blurb-content"><?php echo esc_attr($total_orders); ?></div>

</div>

</div>

<div class="blurb">

    <div class="blurb-inner">

<img src="<?php echo plugin_dir_url(__FILE__) . 'icons/med-risk.svg'; ?>"><h3>Medium Risk Orders</h3>

    <div class="blurb-content"><?php echo esc_attr($number_of_medium_risk_orders24); ?></div>

</div>

</div>

<div class="blurb">

    <div class="blurb-inner">

<img src="<?php echo plugin_dir_url(__FILE__) . 'icons/high-risk.svg'; ?>"><h3>High-Risk Orders on Hold</h3>

    <div class="blurb-content"><?php echo esc_attr($number_of_high_risk_orders_hold24); ?></div>

</div>

</div>

<div class="blurb">

    <div class="blurb-inner">

<img src="<?php echo plugin_dir_url(__FILE__) . 'icons/orders-cancelled.svg'; ?>"><h3>Fraudulent Orders Cancelled</h3>

    <div class="blurb-content"><?php echo esc_attr($number_of_high_risk_orders_cancelled24); ?></div>

</div>

</div>

<div class="blurb">

    <div class="blurb-inner">

<img src="<?php echo plugin_dir_url(__FILE__) . 'icons/money-risk.svg'; ?>"><h3>High-Risk Net Transaction</h3>

    <div class="blurb-content"><?php echo esc_attr($currency . $high_risk_transaction_amt24); ?></div>

</div>

</div>

<div class="blurb">

    <div class="blurb-inner">

<img src="<?php echo plugin_dir_url(__FILE__) . 'icons/emails-blocked.svg'; ?>"><h3>Emails Blocked</h3>

    <div class="blurb-content"><?php echo esc_attr(count($block_emails)); ?></div>

</div>

</div>

<div class="blurb">

    <div class="blurb-inner">

<img src="<?php echo plugin_dir_url(__FILE__) . 'icons/paypal.svg'; ?>"><h3>Paypal Verification Required</h3>

    <div class="blurb-content"><?php echo esc_attr($number_of_paypal_verification_orders); ?></div>

</div>

</div>

    </div>

    </div>

    



    <div class="dash-row second">

<div class="dash-section-50 recent-orders">

   <table>

<thead>

  <tr>

    <th></th>

    <th>Name</th>

    <th><?php echo esc_attr($currency); ?> Spent</th>

    <th>Status</th>

  </tr>

</thead>

<tbody>

<?php
$result = $wpdb->get_results(
    "SELECT * FROM $wpdb->posts 
                WHERE post_type = 'shop_order'
                ORDER BY ID DESC LIMIT 10
    "
);
if (! empty($result)) {
    foreach ($result as $order) {
        $billing_first_name = get_post_meta($order->ID, '_billing_first_name', true);
        $billing_last_name = get_post_meta($order->ID, '_billing_last_name', true);
        $order_total = get_post_meta($order->ID, '_order_total', true);
        $order_currency = get_post_meta($order->ID, '_order_currency', true);
        $order_status = $order->post_status;

        $wc_af_score = intval(get_post_meta($order->ID, 'wc_af_score', true));
        $meta = WC_AF_Score_Helper::get_score_meta($wc_af_score);
        $risk_score_class = '';
        if ('Low Risk' == $meta['label']) {
            $risk_score_class = 'low-risk-icon';
        }
        if ('Medium Risk' == $meta['label']) {
            $risk_score_class = 'med-risk-icon';
        }
        if ('High Risk' == $meta['label']) {
            $risk_score_class = 'high-risk-icon';
        }
        if (0 == $wc_af_score) {
            $risk_score_class = 'high-risk-icon';
        }

        switch ($order_status) {
            case 'wc-pending':
                $status = 'Pending payment';
                break;
            case 'wc-processing':
                $status = 'Processing';
                break;
            case 'wc-on-hold':
                $status = 'On hold';
                break;
            case 'wc-completed':
                $status = 'Completed';
                break;
            case 'wc-cancelled':
                $status = 'Cancelled';
                break;
            case 'wc-refunded':
                $status = 'Refunded';
                break;
            case 'wc-failed':
                $status = 'Failed';
                break;
            default:
                $status = '';
        }
        ?>
  <tr>

    <td><div class="table-icon <?php echo $risk_score_class; ?>"></div></td>

    <td><?php echo esc_attr($billing_first_name) . ' ' . esc_attr($billing_last_name); ?></td>

    <td><?php echo get_woocommerce_currency_symbol($order_currency) . esc_attr($order_total); ?></td>

    <td><?php echo esc_attr($status); ?></td>

  </tr>
        <?php
    }
}
?>

</tbody>

</table>

    

</div>   



<div class="dash-section-50 pie-chart">

    <h2 style="color:white">Orders Breakdown</h2>

    <div class="chart-wrapper">

  <canvas id="barChart"></canvas>  

  </div>

    

</div>   

</div>
<script>

    var canvas = document.getElementById("barChart");

var ctx = canvas.getContext('2d');



// Global Options:

 Chart.defaults.global.defaultFontColor = 'white';

 Chart.defaults.global.defaultFontSize = 16;





var data = {



    labels: ["Low Risk ", "Medium Risk", "High Risk"],

      datasets: [

        {

            fill: true,

            fontColor: 'green',

            backgroundColor: [

                '#5CE593',

                '#E0B826',

                '#E25D71'],

            data: [<?php echo esc_attr($number_of_low_risk_orders); ?>, <?php echo esc_attr($number_of_medium_risk_orders); ?>, <?php echo esc_attr($number_of_high_risk_orders); ?>]

        }

    ]

};



// Notice the rotation from the documentation.



var options = {

    responsive: true,

    maintainAspectRatio: false, 

        title: {

                  display: true,



              },

        rotation: -0.7 * Math.PI

        

};





// Chart declaration:

var myBarChart = new Chart(ctx, {

    type: 'pie',

    data: data,

    options: options,

    responsive:true,

maintainAspectRatio: false

});

    

</script>



    <script type="text/javascript">

    

    // Global Options:

 Chart.defaults.global.defaultFontColor = 'white';

 Chart.defaults.global.defaultFontSize = 16;

 new Chart(document.getElementById("bar-chart-grouped"), {

    type: 'bar',

    data: {

      labels: [
      <?php
        foreach ($last7_days as $day) {
            echo "'" . esc_attr($day) . "',";
        };
        ?>
        ],

      datasets: [

        {

          label: "Low Risk",

          backgroundColor: "#5CE593",

          data: [
          <?php
            foreach ($low_week_arr as $score) {
                echo "'" . esc_attr($score) . "',";
            };
            ?>
            ]

        }, {

          label: "Medium Risk",

          backgroundColor: "#E0B826",

          data: [
          <?php
            foreach ($medium_week_arr as $score) {
                echo "'" . esc_attr($score) . "',";
            };
            ?>
            ]

        }, { 

            label: "High Risk",

          backgroundColor: "#E25D71",

          data: [
          <?php
            foreach ($high_week_arr as $score) {
                echo "'" . esc_attr($score) . "',";
            };
            ?>
            ]

        }

      ]

    },

    options: {

      responsive: true,

    maintainAspectRatio: false

       

    }

});

    </script>

    

    <style>

    

    div#wpwrap {

    background: #000!important;

    fill: #fff!important;

}

.chart-wrapper {

    display: block!important;

    height: 50vh;

}



.dash-section-50 {

    padding: 20px;

    border-radius: 20px;

    background: #1f1e27;

    display: inline-grid;

    vertical-align: top;

}

.dash-section-50 h2 {

    font-size: 30px;

    display: block;

    margin-bottom:20px;

}

.dash-section-50.recent-orders th {

    font-size: 24px;

    padding-bottom: 15px;

}

.dash-section-50,.dash-section-50 h3, dash-section-50 h2  { color: white!important; }

.metric-box {

    display: inline-grid;

    color: black;

    text-align: center;

    padding: 20px;

    width: 25%;

    border-radius: 20px;

    margin: 40px 6px;

    font-size: 23px;

}



.dash-section-50 td, .dash-section-50 thead {

    font-size: 18px;

    line-height: 1.5em;

    text-align: left;

}



.metric-box, .metric-box h2 {

    color: white!important;

}



.metric-box.metric-style1 {

    background: #5c73e5;

}

.metric-box.metric-style2 {

    background: #43b370;

}

.metric-style3 {

    background: #e0b826;

}

.metric-style4 {

    background: #e25d71;

}

.metric-box h2 {

    font-size: 34px;

    margin-top: 20px;

    margin-bottom: 20px;

}

.dash-section-50 {

    margin: 10px;

}

canvas#bar-chart-grouped {

    max-width:100%!important;

}

.blurb h3, .blurb-content, blurb img {

    display: inline-table!important;

    font-size: 17px;

}



.blurb {

    border-bottom: 1px solid #353535;

}

.blurb-inner {

    max-width: 500px;

}



.blurb img {

    width: 22px;

    margin-top: 12px;

    padding-right: 20px;

    padding-left: 10px;

    transform: translate(0px, 5px);

}



.blurb-content {

    padding-top: 15px;

    padding-right: 20px;

    float: right;

}









.med-risk-icon {

    background: #e0b826;

}



.low-risk-icon {

    background: #5ce593;

}



.high-risk-icon {

    background: #e25d71;

}

.table-icon {

    width: 14px;

    height: 14px;

    border-radius: 100%;

    margin-right: 20px;

}

.dash-row {

    display: flex;

    max-width: 1450px;

    margin: 0 auto;

}



.second .dash-section-50 {

    display: flex;

    flex:1;

 }

 

 .dash-section-50.pie-chart {

    display: block;

}

 

 .bar-chart, .dash-stats { width:50%; }

 



 

 table { 

    border-collapse: collapse; 

}

.second td:nth-of-type(2), .second td:nth-of-type(3), .second td:nth-of-type(4) {

        padding-right: 70px;

    padding-top: 12px;

    padding-bottom: 12px;

}

.second tr, .second td {

    border-bottom: 1px solid #353535!important;

}



.chart-wrapper {

    max-width: 700px;

}



.metric-box img {

    height: 80px;

    margin: 0 auto;

}



.pie-chart .chart-wrapper { max-width:600px!important; }



@media only screen and (max-width: 1230px) {

.dash-row { display: block; }

.chart-wrapper { max-width:100%; }

.bar-chart, .dash-stats {

    width: 100%; }

 .dash-section-50 {

    max-width: -webkit-fill-available;

}



.blurb img {

        transform: none;

            padding-left: 0!important;

}



.dash-section-50.dash-stats {

    display: block;

}



.blurb {

    display: inline-grid;

    width: 27%;

    border-bottom: 0;

    background: #0e0e13;

    margin: 1%;

    border-radius: 12px;

    padding: 2%;

    min-height: 120px;

}

.blurb h3, .blurb-content {

    width: initial;

    min-height: 65px;

}



.second td:nth-of-type(2), .second td:nth-of-type(3), .second td:nth-of-type(4) {

    width: 30vw;

}







.metric-box {

    display: inline-grid;

    text-align: center;

    padding: 0;

    width: 24%;

    border-radius: 0;

    margin: 0;

    font-size: 23px;

    min-height: 150px;

}







.blurb-content {

    padding-top: 15px;

    padding-right: 20px;

    float: none;

    display: block!important;

    width: 100%!important;

    font-size: 24px;

}

     .blurb img {

    width: 43px;

    margin-top: 12px;

    padding-right: 20px;

    padding-left: 10px;

    display: block;

}



.blurb {

        min-height: 180px;

        transition:.3s;

}

.blurb:hover {

    background: black;

    transition: .3s;

}

div#wpcontent {

    padding-left: 0!important;

}

}

@media only screen and (max-width: 520px) {
	 .dash-section-50 h2 {
		line-height: 54px;
	 }
 }

 @media only screen and (max-width: 830px) {

 .blurb {

    width: 43%;

    min-height: 170px;

}

     .blurb-content {

    padding-top: 15px;

    padding-right: 20px;

    float: none;

    display: block!important;

    width: 100%!important;

    font-size: 24px;

}

     .blurb img {

    width: 43px;

    margin-top: 12px;

    padding-right: 20px;

    padding-left: 10px;

    display: block;

}

.metric-box {

    display: inline-grid;

    text-align: center;

    padding: 0;

    width: 47%;

    margin: 1%;

    font-size: 23px;

    min-height: 100px;

    padding-bottom: 20px;

    border-radius: 12px;

    margin-top: 8px;

    padding-top: 10px;

}



.second tr, .second td {

    border-bottom: 1px solid #353535!important;

    max-width: 29px;

}
.dash-section-50.recent-orders {

    overflow-x: scroll;

}
</style>
