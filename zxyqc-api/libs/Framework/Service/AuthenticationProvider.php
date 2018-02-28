<?php

namespace Framework\Service;

use Firebase\JWT\JWT;

class AuthenticationProvider
{
    private $secret;
    /**
     * @var array
     */
    private $keys;

    private $token;

    public function __construct($secret, array $keys = array())
    {
        $this->secret = $secret;
        $this->keys = $keys;
    }

    public function generateToken($apiKey, $data)
    {
        $issuedAt = time();
        $data = array(
            "iat" => $issuedAt,
            'nbf' => $issuedAt,
//            'exp' => $issuedAt + (60 * 60 * 60),
            "iss" => md5($apiKey .$issuedAt),
            'user' => $data
        );

        return JWT::encode($data, $apiKey . $this->secret, 'HS256');
    }

    public function isValid($apiKey, $authorizationHeader)
    {
        try {

            $this->token = JWT::decode($authorizationHeader, $apiKey . $this->secret, array('HS256'));

            return true;

        } catch (\Exception $e) {
            $this->token = null;
            return false;
        }
    }

    public function getUser() {
        return null === $this->token ? null : $this->token->user;
    }
}