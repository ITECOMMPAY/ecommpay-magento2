<?php

namespace Ecommpay\Payments\Plugin;

use \Magento\Framework\App\State;

/**
 * Class FileValidator
 *
 * Special override file-validator for enable symlinked templates from module (for development mode)
 */
class FileValidator
{
    /**
     *
     * @var State
     */
    protected $_appState;

    public function __construct(State $appState)
    {
        $this->_appState = $appState;
    }

    public function afterIsValid($subject, $result)
    {
        switch ($this->_appState->getMode()) {
            case State::MODE_DEVELOPER:
                return true;
            case State::MODE_DEFAULT:
                return $result;
            case State::MODE_PRODUCTION:
                return $result;
        }
    }
}
