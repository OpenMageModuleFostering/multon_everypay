<?php

class Multon_Everypay_Model_Source_ApiUrl
{
	public static $urlList = array(
		'TEST' => 'https://igw-demo.every-pay.com/',
		'LIVE' => 'https://pay.every-pay.eu/'
	);

	public function toOptionArray()
	{
		$arr = array();
		$urls = self::$urlList;

		foreach ($urls as $key => $url)
			$arr[] = array(
				'value' => $url,
				'label' => $key,
			);

		return $arr;
	}

}
