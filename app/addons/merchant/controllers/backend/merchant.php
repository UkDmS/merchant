<?php
use Tygh\Registry;
class Merchant
{
    public function get_image($id)
    {
        $detailed_id = db_get_fields("
            SELECT
                detailed_id
            FROM
                ?:images_links
            WHERE
                object_id='$id'");
        $image_path = fn_get_image($detailed_id[0],'detailed') ;
        return $image_path['https_image_path'];
    }
    public function get_url_page($id,$type)
    {
        $url = db_get_fields("
            SELECT
                name
            FROM
                ?:seo_names
            WHERE
                object_id='$id'
            AND
                type='$type'");
        return $url[0];
    }
    public function get_category($id)
    {
        // получаем алиас продукта
        $name = $this->get_url_page($id,'p');
        // получаем категорию продукта
        $category = db_get_fields("SELECT category_id FROM ?:products_categories where product_id='$id'");
        // получаем путь категории
        $parent = db_get_fields("SELECT id_path FROM ?:categories where category_id='$category[0]'");
        // разбиваем, чтобы получить алиасы каждой категории
        $path = explode("/",$parent[0]);
        $i=0;
        foreach($path as $item)
        {
           $part_path[$i] = $this->get_url_page($item,'c');
           $i++;
        };
        // добавляем алиас продукта
        array_push($part_path, $name);
        // формируем урл без домена
        $part_path = implode("/",$part_path);
        $full_path = "https://".$_SERVER["SERVER_NAME"]."/".$part_path."/";
        return $full_path;
    }
}

$merchant = new Merchant();
if ($mode == 'manage') {
    $data = 2;
    $view = Registry::get('view');
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product = db_get_array("
    SELECT
        ?:products.amount,
        ?:products.status,
        ?:product_descriptions.product_id,
        ?:product_descriptions.product,
        ?:product_descriptions.short_description,
        ?:product_descriptions.full_description,
        ?:product_prices.price
    FROM
        ?:product_descriptions,
        ?:products,
        ?:product_prices
    WHERE
        ?:product_descriptions.product_id=?:products.product_id
    AND
        ?:products.status='A'
    AND
        ?:product_prices.product_id=?:product_descriptions.product_id",
    array('product_id', 'product','short_description','status','amount','full_description'));
    $s = "<?xml version='1.0'?>"."\r\n"."<rss xmlns:g='http://base.google.com/ns/1.0' version='2.0'>"."\r\n";
    $s.= "<channel>"."\r\n";
    $s.= "<title>merchant</title>"."\r\n";
	$s.= "<link>https://glavdacha.ru</link>"."\r\n";
    foreach($product as $item)
    {
        $s.="<item>"."\r\n";
      	$s.="<g:id>".$item['product_id']."</g:id>"."\r\n";
		$s.="<g:title><![CDATA[".$item['product']."]]></g:title>"."\r\n";
        if($item['short_description']=='')
		$s.="<g:description><![CDATA[".$item['full_description']."]]></g:description>"."\r\n";
        else
		$s.="<g:description><![CDATA[".$item['short_description']."]]></g:description>"."\r\n";
        $s.="<g:link>".$merchant->get_category($item['product_id'])."</g:link>"."\r\n";
        $s.="<g:image_link>".htmlentities($merchant->get_image($item['product_id']))."</g:image_link>"."\r\n";
        $s.="<g:condition>new</g:condition> "."\r\n";
        if($item['amount']>0)
        $s.="<g:availability>in stock</g:availability>"."\r\n";
        else
        $s.="<g:availability>out of stock</g:availability>"."\r\n";
        $s.="<g:price>".$item['price']." RUB</g:price>"."\r\n";
        $s.="</item>"."\r\n";
    }
    $s.="</channel>"."\r\n";
    $s.="</rss>";
    $output_file = $_SERVER["DOCUMENT_ROOT"]."/merchant.xml";
    file_put_contents($output_file, $s);

}