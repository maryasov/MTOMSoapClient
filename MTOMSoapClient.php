<?php

/**
 * Class MTOMSoapClient
 *
 * This class overrides some SoapClient methods to implement MTOM for PHP.
 * It decodes XML and integrate attchment in the XML response.
 *
 * @author Alexandre D. <debuss-a>
 * @version 1.0.0
 */
class MTOMSoapClient extends SoapClient
{

    /**
     * Override SoapClient to add MTOM decoding on responses.
     *
     * It replaces the :
     *      <xop:Include href="cid:d08bab58-dfea-43f0-8520-477d4c5e0677-103@cxf.apache.org" xmlns:xop="http://www.w3.org/2004/08/xop/include"/>
     * By the binary code contained in attachment
     *      Content-ID: <d08bab58-dfea-43f0-8520-477d4c5e0677-103@cxf.apache.org>
     *
     * Note that the binary in converted to base64 with base64_encode().
     *
     * @link http://php.net/manual/en/soapclient.dorequest.php
     * @param string $request
     * @param string $location
     * @param string $action
     * @param int $version
     * @param int $one_way
     * @return string The XML SOAP response with <xop> tag replaced by base64 corresponding attachment
     * @throws Exception
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $response = parent::__doRequest($request, $location, $action, $version, $one_way);

        $xml_reponse = null;

        // Catch XML response
        preg_match('/<soap[\s\S]*nvelope>/', $response, $xml_reponse);

        if (!is_array($xml_reponse) || !count($xml_reponse)) {

            throw new Exception('No XML has been found.');

        }

        $xml_reponse = reset($xml_reponse);

        // Look if xop then replace by base64_encode(binary)
        $xop_elements = null;
        preg_match_all('/<xop[\s\S]*?\/>/', $response, $xop_elements);
        $xop_elements = reset($xop_elements);

        if (is_array($xop_elements) && count($xop_elements)) {

            foreach ($xop_elements as $xop_element) {

                // Get CID
                $cid = null;
                preg_match('/cid:([0-9a-zA-Z-]+)@/', $xop_element, $cid);
                $cid = $cid[1];

                // Get Binary
                $binary = null;
                preg_match('/Content-ID:[\s\S].+?'.$cid.'[\s\S].+?>([\s\S]*?)--uuid/', $response, $binary);
                $binary = ltrim($binary[1]);

                $binary = base64_encode($binary);

                // Replace xop:Include tag by base64_encode(binary)
                // Note: SoapClient will automatically base64_decode(binary)
                $xml_reponse = preg_replace('/<xop:Include[\s\S]*cid:'.$cid.'@[\s\S]*?\/>/', $binary, $xml_reponse);

            }

        }

        return $xml_reponse;
    }
}
