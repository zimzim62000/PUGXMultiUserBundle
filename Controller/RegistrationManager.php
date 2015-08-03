<?php

namespace PUGX\MultiUserBundle\Controller;

use Centaure\Bundles\UserBundle\Event\UpdateUserEvent;
use PUGX\MultiUserBundle\Model\UserDiscriminator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use FOS\UserBundle\Controller\RegistrationController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use PUGX\MultiUserBundle\Form\FormFactory;

class RegistrationManager
{
    /**
     *
     * @var \PUGX\MultiUserBundle\Model\UserDiscriminator
     */
    protected $userDiscriminator;

    /**
     *
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    /**
     *
     * @var \FOS\UserBundle\Controller\RegistrationController
     */
    protected $controller;

    /**
     *
     * @var \PUGX\MultiUserBundle\Form\FormFactory
     */
    protected $formFactory;

    /**
     *
     * @param \PUGX\MultiUserBundle\Model\UserDiscriminator $userDiscriminator
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param \FOS\UserBundle\Controller\RegistrationController $controller
     * @param \PUGX\MultiUserBundle\Form\FormFactory $formFactory
     */
    public function __construct(
        UserDiscriminator $userDiscriminator,
        ContainerInterface $container,
        RegistrationController $controller,
        FormFactory $formFactory
    ) {
        $this->userDiscriminator = $userDiscriminator;
        $this->container = $container;
        $this->controller = $controller;
        $this->formFactory = $formFactory;
    }

    /**
     *
     * @param string $class
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function register($class)
    {
        $this->userDiscriminator->setClass($class);

        $this->controller->setContainer($this->container);
        $result = $this->controller->registerAction($this->container->get('request'));
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        $template = $this->userDiscriminator->getTemplate('registration');
        if (is_null($template)) {
            $template = 'FOSUserBundle:Registration:register.html.twig';
        }

        $form = $this->formFactory->createForm();

        return $this->container->get('templating')->renderResponse(
            $template,
            array(
                'form' => $form->createView(),
                'new' => true
            )
        );
    }

    /**
     *
     * @param string $class
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function update($id)
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        /** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
        $dispatcher = $this->container->get('event_dispatcher');
        $em = $this->container->get('doctrine')->getManager();

        /** @var $user \Centaure\Bundles\UserBundle\User */
        $user = $em->getRepository('CentaureBundlesUserBundle:User')->find($id);
        $role1 = $user->getRoles();

        $discriminator = $this->container->get('pugx_user.manager.user_discriminator');
        $discriminator->setClass(get_class($user));
        $userManager = $this->container->get('pugx_user_manager');

        $form = $this->container
            ->get('pugx_multi_user.profile_form_factory')->createForm();
        $form->setData($user);

        if ('POST' === $request->getMethod()) {

            $form->handleRequest($request);

            if ($form->isValid()) {

                $userManager->updateUser($user);

                $this->container->get('session')->getFlashBag()->add('success', 'flashbag.success.update');

                $role2 = $user->getRoles();

                $event = new UpdateUserEvent(array($role1, $role2), $user);
                $dispatcher->dispatch('centaure_user_user.event.update_user', $event);

                return new RedirectResponse($this->container->get('router')->generate('centaure_user_user'));
            }
        }

        $folder = substr(strrchr(get_class($user), '\/[a-zA-Z]$'), 1);

        return $this->container->get('templating')->renderResponse(
            'CentaureBundlesUserBundle:' . $folder . ':register.html.twig',
            array('form' => $form->createView(), 'new' => false)
        );
    }
}
