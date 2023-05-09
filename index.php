<?php 
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

include 'includes/config.php';

if(!isset($_GET['s']) || ($_GET['s']!='bsh62kshd82SdkMnb23Ja01Mkl' && $_GET['s']!='GsR61Nshm02LakPjb23Ja76G98') ) {
    die("Invalid request");
}

if($_GET['s']=='GsR61Nshm02LakPjb23Ja76G98') {
    $secretKey = "THEGOLDENLIFE";
} else {
    $secretKey = "SLAPNUTS";
}

$message = json_decode(file_get_contents('php://input'));

$encrypted = $message->{'notification'};
$iv = $message->{'iv'};

$decrypted = trim(openssl_decrypt(base64_decode($encrypted),'AES-256-CBC', substr(sha1($secretKey), 0, 32), OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, base64_decode($iv)), "\0..\32");

$sanitizedData = utf8_encode($decrypted);

$receipt = json_decode($decrypted);


$vendor = $receipt->vendor;
$role = $receipt->role;
$version = $receipt->version;
$ttype = $receipt->transactionType;
$db->query("INSERT INTO `cb_notifications` (`cb_notification_json`) VALUES ('".mysqli_real_escape_string($db, $decrypted)."');");

$bumps = [];

if((
    $vendor == 'unlockfb' || $vendor == 'unlockher' || 
    $vendor == 'endofdate' || $vendor == 'realsinn' || $vendor == 'tone024' || $vendor == 'tsbmag' 
    ) && $role == 'VENDOR' && $version == '7.0') {
    
    $tdate = $receipt->transactionTime;
	$newdate = date('c', strtotime($tdate));

	$date = new DateTime($tdate);
    $date->modify('+1 second');
    $bumpdate = $date->format('c');
	
	$transactionid = $receipt->receipt;
    $recievedamount = $receipt->totalAccountAmount;
	$orderamount = $receipt->totalOrderAmount;
	$taxamount = $receipt->totalTaxAmount;

    if(count($receipt->lineItems) > 0) {
		foreach($receipt->lineItems as $product) {
			if($product->lineItemType == 'ORIGINAL') {
				$productid = $product->itemNo;
				if($vendor == 'unlockfb') {
                    $productitle = $product->productTitle;
                    $productamount = $product->accountAmount;
                } else {
                    $productitle = $vendor."-".$product->productTitle;
                    if(isset($product->productDiscount)) {
                        $productamount = $product->productPrice - $product->productDiscount;
                    } else {
                        $productamount = $product->productPrice;
                    }
                }
			} else if($product->lineItemType == 'UPSELL') {
                $upsell_productid = $product->itemNo;
				if($vendor == 'unlockfb') {
                    $upsell_productitle = $product->productTitle;
                    $upsell_productamount = $product->accountAmount;
                } else {
                    $upsell_productitle = $vendor."-".$product->productTitle;
                    if(isset($product->productDiscount)) {
                        $upsell_productamount = $product->productPrice - $product->productDiscount;
                    } else {
                        $upsell_productamount = $product->productPrice;
                    }
                }
            } else if($product->lineItemType == 'BUMP') {
                if($vendor == 'unlockfb') {
                    $bptitle = $product->productTitle;
                    $bumpAmount = $product->accountAmount;
                } else {
                    $bptitle = $vendor."-".$product->productTitle;
                    if(isset($product->productDiscount)) {
                        $bumpAmount = $product->productPrice - $product->productDiscount;
                    } else {
                        $bumpAmount = $product->productPrice;
                    }
                }
				$bumps[] = [
                    'productid' => $product->itemNo,
                    'productitle' => $bptitle,
                    'productamount' => $bumpAmount
                ];
			}
		}
	}

    

    $email = $receipt->customer->billing->email ?? '';
	$firstname = $receipt->customer->billing->firstName ?? '';
	$lastname = $receipt->customer->billing->lastName ?? '';
	
	$phonenumber = $receipt->customer->billing->phoneNumber ?? '';
	
	$country = $receipt->customer->billing->address->country ?? '';
	$zip = $receipt->customer->billing->address->postalCode ?? '';

    if($ttype == 'SALE' || $ttype == 'TEST_SALE' || $ttype=='BILL') {
        $postvars = [
            'email' => $email,
            'firstName' => $firstname,
            'lastName' => $lastname,
            'date' => $newdate,
            'priceFormat' => 'DECIMAL',
            'currency' => 'USD',
        ];
		
		if($phonenumber != '') {
            $postvars['phoneNumbers'][] = $phonenumber;
        }
		
        if(isset($productid)) {
            $postvars['orderId'] = $transactionid;
            $postvars['items'] = [];
            $postvars['items'][] = [
                'name' => $productitle,
                'price' => $productamount,
            ];
            postToHyros( json_encode($postvars) );
        } 
        
        if(isset($upsell_productid)) {
            $postvars['orderId'] = $transactionid."-UPSELL";
            $postvars['items'] = [];
            $postvars['items'][] = [
                'name' => $upsell_productitle,
                'price' => $upsell_productamount,
            ];
            postToHyros( json_encode($postvars) );
        } 
        
        if(count($bumps) > 0) {
            foreach($bumps as $bump) {
				$postvars['date'] = $bumpdate;
                $postvars['orderId'] = $transactionid."-BUMP";
                $postvars['items'] = [];
                $postvars['items'][] = [
                    'name' => $bump['productitle'],
                    'price' => $bump['productamount'],
                ];
                postToHyros( json_encode($postvars) );
            }
        }
    } else if($ttype == 'RFND' || $ttype == 'CGBK') {

    }

}



function postToHyros($postvars) {


    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.hyros.com/v1/api/v1.0/orders");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);

    curl_setopt($ch, CURLOPT_POST, TRUE);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Content-Type: application/json",
    "API-Key: ".HYROS_API_KEY
    ));

    $response = curl_exec($ch);
    //print_r($response);
    curl_close($ch);
    
}

// echo "<pre>";
// print_r($encrypted);
// echo "</pre>";
