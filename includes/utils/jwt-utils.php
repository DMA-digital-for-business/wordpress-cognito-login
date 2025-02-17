<?php

class JwtUtils
{

    public static function jwtTokenIsValid($token): string
    {
        $validationResultCode    = null;
        $validationResultMessage = null;

        // Il token come minimo deve essere presente
        if (! $token || $token === false) {
            $validationResultCode = 'ab794148-00c3-41aa-b6af-656a50eb0e1a';
            error_log('jwt validation failed: token not found');
            return $validationResultCode;
        }

        // Devo avere in configurazione le chiavi pubbliche (un json con un unico oggetto)
        if (! defined('COGNITO_JWT_KEYS')) {
            $validationResultCode = '16e98f05-c842-4eae-a13a-7cfc2b0358a6';
            error_log('jwt validation failed: COGNITO_JWT_KEYS not found');
            return $validationResultCode;
        }
        $cognito_jwt_keys = Cognito_Login_Options::get_plugin_option('COGNITO_JWT_KEYS');

        if (! $cognito_jwt_keys) {
            $validationResultCode = 'b12bef53-85ad-4b97-97fd-b39b15515244';
            error_log('jwt validation failed: cognito jwt keys empty');
            return $validationResultCode;
        }
        $key_container = json_decode($cognito_jwt_keys, true);
        if (! $key_container) {
            $validationResultCode = '6d3f10f8-f6c3-45d2-826e-4336175eb188';
            error_log('jwt validation failed: error decoding cognito jwt keys');
            return $validationResultCode;
        }
        if (! is_array($key_container)) {
            $validationResultCode = '3b8bf2ac-a32e-4307-ae33-4c5de9ca2a43';
            error_log('jwt validation failed: key container is not an array');
            return $validationResultCode;
        }
        if (! isset($key_container["keys"])) {
            $validationResultCode = '215b25ec-2b48-49f7-b176-eb151918772a';
            error_log('jwt validation failed: key container has no keys array');
            return $validationResultCode;
        }
        $available_keys = $key_container["keys"];

        // Ci devono essere tre parti
        $token_parts = self::get_token_components($token);
        if (count($token_parts) !== 3) {
            $validationResultCode = '46111f07-5781-48f1-814d-57899ff69109';
            error_log('jwt validation failed: token has not 3 parts');
            return $validationResultCode;
        }

        // le 3 stringhe, in base 64, corrispondenti a header, payload e signature
        list($header_encoded, $payload_encoded, $signature_encoded) = $token_parts;

        // Header (array)
        $jwt_header = self::tokenCompoentToArray(($header_encoded));
        if (! is_array($jwt_header)) {
            $validationResultCode = '4cfc2995-efc9-4b35-bce9-61580b418f7e';
            error_log('jwt validation failed: header is not an array');
            return $validationResultCode;
        }

        if (! isset($jwt_header["kid"])) {
            $validationResultCode = '3a4f3856-a70b-40af-8555-41b1e578ceab';
            error_log('jwt validation failed: header has no kid field');
            return $validationResultCode;
        }

        //  Id della chiave usata per firmare il jwt
        $kid = $jwt_header["kid"];
        if (! $kid) {
            $validationResultCode = '0c618834-3c4b-480b-8c05-eabba55c3825';
            error_log('jwt validation failed: kid is empty');
            return $validationResultCode;
        }

        // Trovo la chiave pubblica di cognito usata corrispondente al $kid
        // Qui è restituita già in formato PEM
        $public_key = self::getPublicKeyAsPem($available_keys, $kid);

        // Se non ho, tra le chiavi pubbliche in mio possesso, la chiave
        // usata per firmare il jwt, rifiuto il token
        if (! $public_key) {
            $validationResultCode = 'ce80daa6-35a9-4fe5-8b45-33cbae8a5606';
            error_log('jwt validation failed: public key not found');
            return $validationResultCode;
        }

        // echo "<!--\n" . $public_key . "-->\n";

        // Firma, come stringa
        $signature      = self::base64UrlDecode($signature_encoded);
        $data_to_verify = "$header_encoded.$payload_encoded";

        // Questa verifica rileva QUALSIASI manomissione al jwt
        $verified = openssl_verify($data_to_verify, $signature, $public_key, OPENSSL_ALGO_SHA256);

        if (! $verified) {
            $validationResultCode = 'ee2ebb76-5fa1-4880-90a4-3d1395f17ddd';
            error_log('jwt validation failed: signature not verified');
            return $validationResultCode;
        }

        // Nel payload, deve esserci il camppo 'exp' che indica la validità massima del token
        $jwt_payload = self::tokenCompoentToArray(($payload_encoded));
        if (! isset($jwt_payload["exp"])) {
            $validationResultCode = '2d94e33f-194a-488d-b9b7-6e18cbb976c7';
            error_log('jwt validation failed: payload has no exp field');
            return $validationResultCode;
        }
        // Il token non deve essere scaduto
        $exp_time     = $jwt_payload['exp'];
        $current_time = time();
        if ($current_time > $exp_time) {
            $validationResultCode = '4d1d28cb-7eaa-401d-9f7b-aa549b722b05';
            error_log('jwt validation failed: token is expired');
            return $validationResultCode;
        }

        // Nel token ci deve essere il campo
        if (! isset($jwt_payload["email_verified"]) || ! $jwt_payload["email_verified"]) {
            $validationResultCode = 'ff399d99-09e3-48b4-8486-b7cb767f742';
            error_log('jwt validation failed: email not verified');
            return $validationResultCode;
        }

        // Direi che a questo punto sono sicuro che il token sia valido, a livello di firma almeno
        return "OK";
    }

    public static function get_token_components($token)
    {
        return explode('.', $token);
    }

    // Passando uno dei componenti del jwt, come stringa in base64, la restituisce
    // come array associativo
    public static function tokenCompoentToArray($token_component)
    {
        return json_decode(self::base64UrlDecode($token_component), true);
    }

    public static function getPublicKeyAsPem($available_keys, $json_key_id)
    {
        $public_key_pem = null;
        foreach ($available_keys as $key) {

            if ($key['kid'] === $json_key_id) {
                // Conversione della chiave pubblica dal formato JSON a PEM
                $n = $key['n'];
                $e = $key['e'];

                // echo "\n<!-- Uso n `$n`, ed e `" . $e . "` -->\n";
                $public_key_pem = "-----BEGIN PUBLIC KEY-----\n" . self::convertRsaKeyToPem($n, $e) . "-----END PUBLIC KEY-----\n";
                // echo "\n<!-- Public key\n" . $public_key_pem . "\n-->\n";

                break;
            } else {
                // echo "\n<!-- Cerco `$json_key_id`, trovato `" . $key["kid"] . "` -->\n";
            }
        }
        return $public_key_pem;
    }
    public static function convertRsaKeyToPem($n, $e)
    {
        $modulus  = self::base64UrlDecode($n);
        $exponent = self::base64UrlDecode($e);

                                           // ASN.1 Sequence
        $modulus      = "\x00" . $modulus; // Ensure positive integer (leading 0)
        $modulus      = self::asn1EncodeInteger($modulus);
        $exponent     = self::asn1EncodeInteger($exponent);
        $rsaPublicKey = self::asn1EncodeSequence($modulus . $exponent);

        // Add RSA OID
        $rsaOID           = self::asn1EncodeSequence("\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01\x05\x00");
        $rsaPublicKeyInfo = self::asn1EncodeSequence($rsaOID . self::asn1EncodeBitString($rsaPublicKey));

        return chunk_split(base64_encode($rsaPublicKeyInfo), 64, "\n");
    }

    public static function asn1EncodeInteger($data)
    {
        return "\x02" . self::asn1Length(strlen($data)) . $data;
    }

    public static function asn1EncodeSequence($data)
    {
        return "\x30" . self::asn1Length(strlen($data)) . $data;
    }

    public static function asn1EncodeBitString($data)
    {
        return "\x03" . self::asn1Length(strlen($data) + 1) . "\x00" . $data;
    }

    public static function asn1Length($length)
    {
        if ($length < 128) {
            return chr($length);
        }
        $temp = '';
        while ($length > 0) {
            $temp = chr($length & 0xFF) . $temp;
            $length >>= 8;
        }
        return chr(0x80 | strlen($temp)) . $temp;
    }

    public static function base64UrlDecode($data)
    {
        $urlSafeData = strtr($data, '-_', '+/');
        $paddedData  = str_pad($urlSafeData, strlen($urlSafeData) % 4, '=', STR_PAD_RIGHT);
        return base64_decode($paddedData);
    }
}
