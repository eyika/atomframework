<?php
class Cookie
{
    protected $name;
    protected $value;
    protected $expiry;
    protected $path;
    protected $domain;
    protected $secure;
    protected $httpOnly;

    public function __construct(
        $name, 
        $value = '', 
        $expiry = 0, 
        $path = '/', 
        $domain = '', 
        $secure = false, 
        $httpOnly = true
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->expiry = $expiry;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
    }

    // Method to create a new instance with a modified value
    public function withValue($value)
    {
        $clone = clone $this;
        $clone->value = $value;
        return $clone;
    }

    // Convert the cookie object to a string suitable for `Set-Cookie` header
    public function __toString()
    {
        return sprintf(
            '%s=%s; Expires=%s; Path=%s; Domain=%s; %s%s',
            $this->name,
            urlencode($this->value),
            gmdate('D, d-M-Y H:i:s T', $this->expiry),
            $this->path,
            $this->domain ?: '',
            $this->secure ? 'Secure; ' : '',
            $this->httpOnly ? 'HttpOnly' : ''
        );
    }
}
