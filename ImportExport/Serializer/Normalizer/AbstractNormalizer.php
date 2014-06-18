<?php

namespace OroCRM\Bundle\ZendeskBundle\ImportExport\Serializer\Normalizer;

use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

abstract class AbstractNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    /**
     * @var PropertyAccessor
     */
    static private $propertyAccessor;

    /**
     * @var SerializerInterface|NormalizerInterface|DenormalizerInterface
     */
    protected $serializer;

    /**
     * @var array
     */
    private $fieldRules;

    /**
     * @var string
     */
    private $primaryFieldName;

    /**
     * List of rules that declare (de)normalization, for example
     *
     * array(
     *  array(
     *      'name' => 'id',
     *      'primary' => true,
     *  ),
     *  'name',
     *  'created_at' => array(
     *      'type' => 'DateTime',
     *      'context' => array('type' => 'datetime'),
     *  ),
     * );
     *
     * @return array
     */
    abstract protected function getFieldRules();

    /**
     * Class name of object of this normalizer
     *
     * @return string
     */
    abstract protected function getTargetClassName();

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        throw new \BadMethodCallException('Method is not implemented.');
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param mixed $format
     * @param array $context
     * @return mixed
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $fieldRules = $this->getProcessedFieldRules();

        if (!is_array($data)) {
            if ($this->primaryFieldName) {
                $data = array($this->primaryFieldName => $data);
            } else {
                return $this->createNewObject();
            }
        }

        $object = $this->createNewObject();

        foreach ($fieldRules as $field) {
            if (!array_key_exists($field['name'], $data)) {
                continue;
            }

            if (!isset($field['type'])) {
                $value = $data[$field['name']];
            } else {
                $value = $this->serializer->denormalize(
                    $data[$field['name']],
                    $field['type'],
                    $format,
                    array_merge($context, $field['context'])
                );
            }

            $this->getPropertyAccessor()->setValue($object, $field['name'], $value);
        }

        return $object;
    }

    /**
     * Creates new object of target class
     *
     * @return mixed
     */
    protected function createNewObject()
    {
        $className = $this->getTargetClassName();
        return new $className;
    }

    /**
     * List of rules that declare (de)normalization
     *
     * @return array
     */
    protected function getProcessedFieldRules()
    {
        if (null == $this->fieldRules) {
            $this->fieldRules = array();
            foreach ($this->getFieldRules() as $key => $field) {
                if (is_string($field)) {
                    $field = array('name' => $field);
                }

                if (!isset($field['name'])) {
                    $field['name'] = $key;
                }

                if (!isset($field['context'])) {
                    $field['context'] = array();
                }

                if (!empty($field['primary'])) {
                    $this->primaryFieldName = $field['name'];
                }

                $this->fieldRules[] = $field;
            }
        }

        return $this->fieldRules;
    }

    /**
     * @return PropertyAccessor
     */
    protected function getPropertyAccessor()
    {
        if (!self::$propertyAccessor) {
            self::$propertyAccessor = PropertyAccess::createPropertyAccessor();
        }
        return self::$propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        $className = $this->getTargetClassName();
        return $data instanceof $className;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return $type == $this->getTargetClassName();
    }

    /**
     * @param SerializerInterface $serializer
     * @throws InvalidArgumentException
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        if (!$serializer instanceof NormalizerInterface || !$serializer instanceof DenormalizerInterface) {
            throw new InvalidArgumentException(
                sprintf(
                    'Serializer must implement "%s" and "%s"',
                    'Symfony\\Component\\Serializer\\Normalizer\\NormalizerInterface',
                    'Symfony\\Component\\Serializer\\Normalizer\\DenormalizerInterface'
                )
            );
        }
        $this->serializer = $serializer;
    }
}
