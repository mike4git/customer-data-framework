<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace CustomerManagementFrameworkBundle\View\Formatter;

class ObjectWrapper
{
    /**
     * @var mixed
     */
    protected $object;

    /**
     * @param $object
     */
    public function __construct($object)
    {
        $this->object = $object;
    }

    /**
     * @return mixed|string
     */
    public function __toString()
    {
        if (!is_object($this->object)) {
            return $this->object ?? '';
        }

        if (method_exists($this->object, '__toString')) {
            return call_user_func([$this->object, '__toString']);
        }

        return get_class($this->object);
    }
}
