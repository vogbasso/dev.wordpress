<?php

class SFA_Abandoned_Carts_Cart_Exporter {
    
    function export_carts($start_date, $end_date) {
        $table = new SFA_Abandoned_Carts_Table($start_date, $end_date, false);
        $table->prepare_items();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=carts.csv');

        ob_end_clean();
        
        $f = fopen('php://output', 'w'); 

        $headers = array('Cart ID', 'Cart Status', 'Cart Expired', 'Customer / IP', 'Location', 'Email', 'Products', 'Cart Value');
        fputcsv($f, $headers);

        foreach ($table->carts as $line) {
            $data = array($line->get_cart_id(), 
                $line->get_cart_status_without_html(),     
                $line->get_cart_expiry_date(),
                $line->get_cart_customer(),
                $line->get_cart_location(),
                $line->get_cart_email_without_html(),
                $line->get_cart_item_descriptions_without_html(),
                money_format('%i', $line->get_cart_total())
            );

            fputcsv($f, $data);
        }

        exit();
    }
}