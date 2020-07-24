<?php
/*
 * Copyright 2014 IMAGIN Sp. z o.o. - imagin.pl
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints as Assert;

class UploadFileForm
{
    private $form_factory;

    /**
     * @param $form_factory
     */
    public function __construct($form_factory)
    {
        $this->form_factory = $form_factory;
    }

    /**
     * @return mixed
     */
    public function createForm()
    {
        $builder = $this->form_factory;
        $builder = $builder->createBuilder()
            ->add('fileToUpload', FileType::class, array(
                    'required' => true,
                    /*'constraints' => new Assert\File(array(
                            'mimeTypes' => array(
                                'application/xml',
                                'text/plain'
                            )
                    ))*/))
            ->getForm();
        return $builder;
    }
}