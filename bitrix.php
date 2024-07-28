<?php

$api_url = "https://b24-u8i9wx.bitrix24.ru/rest/1/0oqwm1oftg1fmgf3/";
$score_field_name = "ufCrm6_1721814262";

function call_bitrix($method, $params) {
    global $api_url;
    $url = $api_url . $method;
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($curl);
    if (curl_errno($curl)) {
        throw new Exception(curl_error($curl));
    }
    curl_close($curl);

    return json_decode($response, true);
}

function call_bitrix_paginated($method, $params) {
    $start_offset = 0;
    $total_fetched = 0;
    $items = [];
    while (true) {
        $response = call_bitrix(
            $method,
            array_merge(["start" => $start_offset], $params)
        );
        $data = $response["result"];

        if (array_key_exists("items", $data)) {
            $data = $data["items"];
        }
        $total_items = $response["total"];
        $items = array_merge($items, $data);
        $total_fetched += count($data);
        $start_offset += count($data);
        if ($total_fetched >= $total_items) {
            break;
        }
    }
    return $items;
}

function fetch_deals_categories() {
    $data = call_bitrix("crm.category.list", [
        "entityTypeId" => "2"
    ])["result"]["categories"];
    return $data;
}

// 0

$total_deals = call_bitrix("crm.deal.list", [])["total"];
$total_contacts = call_bitrix("crm.contact.list", [])["total"];

echo "total_deals: " . $total_deals . "\n";
echo "total_contacts: " . $total_contacts . "\n";


// 1
$contacts_with_comments = call_bitrix(
    "crm.contact.list",
    ["filter" => ["!COMMENTS" => ""], "select" => ["ID", "COMMENTS"]]
);
$contacts_with_comments_count = $contacts_with_comments["total"];


// 2
$deals_without_contacts = call_bitrix(
    "crm.deal.list",
    ["filter" => ["CONTACT_ID" => ""], "select" => ["ID", "TITLE", "CONTACT_ID"]]
);
$deals_without_contacts_count = $deals_without_contacts["total"];


// 3
$deals = call_bitrix_paginated("crm.deal.list", []);
$deal_categories = fetch_deals_categories();
$category_deal = [];
foreach ($deal_categories as $c) {
    $category_deal[$c["name"]] = count(array_filter($deals, function($d) use ($c) {
        return $d["CATEGORY_ID"] == strval($c["id"]);
    }));
}


// 4
$smart_processes_with_scores = call_bitrix_paginated(
    "crm.item.list",
    ["entityTypeId" => "1038", "filter" => [">{$score_field_name}" => "0"]]
);


$smart_processes_with_scores_sum = array_reduce(
    $smart_processes_with_scores,
    function($carry, $element) use ($score_field_name) {
        return $carry + ($element[$score_field_name] ?? 0);
    },
    0
);

echo "contacts_with_comments_count " . $contacts_with_comments_count . "\n";
echo "deals_without_contacts_count " . $deals_without_contacts_count . "\n";
echo "smart_processes_with_scores_sum " . $smart_processes_with_scores_sum . "\n";
foreach ($category_deal as $category => $count) {
    echo $category . " " . $count . "\n";
}