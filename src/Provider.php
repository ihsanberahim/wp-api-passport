<?php

namespace WpRestPassport;

class Provider{
    public static function register() {
        new RestApi();
    }
}