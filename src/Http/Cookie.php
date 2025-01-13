<?php
class Cookie
{
    protected $name;
    protected $value;
    protected $maxAge;
    protected $path;
    protected $domain;
    protected $secure;
    protected $httpOnly;

    public function __construct(
        $name, 
        $value = '', 
        $maxAge = 0, 
        $path = '/', 
        $domain = '', 
        $secure = false, 
        $httpOnly = true
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->maxAge = $maxAge;
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

    /**
     * Checks if there is a value.
     *
     * @return bool
     */
    public function hasValue()
    {
        return isset($this->value);
    }

    /**
     * Returns the name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the max age.
     *
     * @return int|null
     */
    public function getMaxAge()
    {
        return $this->maxAge;
    }

    /**
     * Checks if there is a max age.
     *
     * @return bool
     */
    public function hasMaxAge()
    {
        return isset($this->maxAge);
    }

    /**
     * Returns the value.
     *
     * @return string|null
     */
    public function getValue()
    {
        return $this->value;
    }

    // Convert the cookie object to a string suitable for `Set-Cookie` header
    public function __toString()
    {
        return sprintf(
            '%s=%s; Expires=%s; Path=%s; Domain=%s; %s%s',
            $this->name,
            urlencode($this->value),
            gmdate('D, d-M-Y H:i:s T', $this->maxAge),
            $this->path,
            $this->domain ?: '',
            $this->secure ? 'Secure; ' : '',
            $this->httpOnly ? 'HttpOnly' : ''
        );
    }
}
