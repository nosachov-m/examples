<?php

namespace NewsBundle\Form\Type;

use Ivory\CKEditorBundle\Form\Type\CKEditorType;
use NewsBundle\Document\News;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'type',
                ChoiceType::class,
                [
                    'label' => 'form.type.type',
                    'choices' => [
                        News::TYPE_NEWS => 'form.type.news',
                        News::TYPE_ANNOUNCEMENT => 'form.type.announcement',
                    ],
                ]
            )
            ->add(
                'title',
                TextType::class,
                [
                    'label' => 'form.title',
                ]
            )
            ->add(
                'content',
                CKEditorType::class,
                [
                    'label' => 'form.content',
                    'config_name' => 'my_config'
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'NewsBundle\Document\News',
            'translation_domain' => 'NewsBundle',
        ]);
    }
}
