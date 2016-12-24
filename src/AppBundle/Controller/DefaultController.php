<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use AppBundle\Entity\File;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller {
    public function indexAction(Request $request) {
        return $this->render('AppBundle::index.html.twig');
    }

    public function signupPageAction(Request $request) {
        return $this->render('AppBundle::signup.html.twig');
    }

    public function loginPageAction(Request $request) {
        return $this->render('AppBundle::login.html.twig');
    }

    public function loginAction(Request $request) {
        $username = $request->request->get('username');
        $password = $request->request->get('password');
        $userRepo = $this->getDoctrine()->getRepository('AppBundle:User');
        $foundUser = $userRepo->findOneByUsername($username);
        if (!$foundUser) {
            $this->addFlash('login-error', 'no-user');
        } else {
            if (password_verify($foundUser->getPassword(), $password)) {
                $this->addFlash('login-error', 'wrong-password');
            } else {
                $role = $foundUser->getRole();
                $this->authenticateUser($username, $role);
                return $this->redirectToRoute('homepage');
            }
        }
        return $this->redirectToRoute('login_page');
    }

    public function logoutAction(Request $request) {
        $this->get('session')->remove('user');
        return $this->redirectToRoute('homepage');
    }

    public function signupAction(Request $request) {
        $username = $request->request->get('username');
        $password = $request->request->get('password');
        $confirmPassword = $request->request->get('confirm-password');
        $userRepo = $this->getDoctrine()->getRepository(User::class);
        $foundUser = $userRepo->findOneByUsername($username);
        $success = true;
        if ($foundUser) {
            $this->addFlash('signup-error', 'username-exists');
            $success = false;
        }
        if ($password != $confirmPassword) {
            $this->addFlash('signup-error', 'passwords-mismatch');
            $success = false;
        }
        if ($success) {
            $user = new User();
            $user->setUsername($username);
            $encodedPassword = password_hash($password, PASSWORD_BCRYPT);
            $user->setPassword($encodedPassword);
            $role = 'user';
            $user->setRole($role);
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($user);
            $em->flush();

            $this->authenticateUser($username, $role);

            return $this->redirectToRoute('homepage');
        }
        return $this->redirectToRoute('signup_page');
    }

    public function addFileAction(Request $request) {
        $filename = $request->request->get('filename');
        $fileRepo = $this->getDoctrine()->getRepository(File::class);
        $foundFile = $fileRepo->findOneByName($filename);
        if (!$foundFile) {
            $em = $this->getDoctrine()->getManager();
            $file = new File();
            $file->setName($filename);
            $em->persist($file);
            $em->flush();
        }
        return $this->redirectToRoute('homepage');
    }

    public function checkUserPermissionAction(Request $request) {
        $username = $request->request->get('username');
        $filename = $request->request->get('filename');
        $checked = $request->request->get('checked');
        $em = $this->getDoctrine()->getManager();
        $fileRepo = $this->getDoctrine()->getRepository(File::class);
        $user = $em->getRepository(User::class)->findOneByUsername($username);
        $file = $fileRepo->findOneByName($filename);
        if ($user and $file) {
            if ($checked == 'true') {
                $user->addFile($file);
            } else {
                $userFiles = $user->getFiles();
                $files = [];
                foreach ($userFiles as $userFile) {
                    if ($userFile->getName() != $filename) {
                        $files[] = $userFile;
                    }
                }
                dump($files);
                $user->setFiles($files);
            }
            $em->flush();
        }
        return $this->redirectToRoute('homepage');
    }

    private function authenticateUser($username, $role) {
        $session = $this->get('session');
        $session->set('user', $username);
        $session->set('role', $role);
    }
}
