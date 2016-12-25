<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use AppBundle\Entity\File;
use AppBundle\Utility\GoogleAuthenticator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller {
    public function indexAction(Request $request) {
        $this->updateSession();
        return $this->render('AppBundle::index.html.twig');
    }

    public function signupPageAction(Request $request) {
        $user = $this->updateSession();
        if ($user) {
            return $this->redirectToRoute('homepage');
        }
        return $this->render('AppBundle::signup.html.twig');
    }

    public function signup2PageAction(Request $request) {
        $user = $this->updateSession();
        if ($user) {
            $secret = $user->getSecret();
            $ga = new GoogleAuthenticator();
            $qr = $ga->getQRCodeGoogleUrl($user->getUsername(), $secret);
            $this->get('session')->remove('signup');
            return $this->render('AppBundle::signup2.html.twig', ['secret' => $secret, 'qr' => $qr]);
        }
        return $this->redirectToRoute('homepage');
    }

    public function loginPageAction(Request $request) {
        $user = $this->updateSession();
        if ($user) {
            return $this->redirectToRoute('homepage');
        }
        return $this->render('AppBundle::login.html.twig');
    }

    public function login2PageAction(Request $request) {
        $tempUser = $this->get('session')->get('temp_user');
        if ($tempUser) {
            return $this->render('AppBundle::login2.html.twig');
        }
        return $this->redirectToRoute('homepage');
    }

    public function contentPageAction(Request $request) {
        $user = $this->updateSession();
        $filename = $request->query->get('filename');
        $fileRepo = $this->getDoctrine()->getRepository(File::class);
        $file = $fileRepo->findOneByName($filename);
        if ($user->getRole() === 'admin' || $user->getFiles()->contains($file)) {
            $content = $this->decryptContent($file->getContent());
            return $this->render('AppBundle::content.html.twig', ['content' => $content]);
        }
        return $this->redirectToRoute('homepage');
    }

    public function loginAction(Request $request) {
        $username = $request->request->get('username');
        $password = $request->request->get('password');
        $userRepo = $this->getDoctrine()->getRepository('AppBundle:User');
        $foundUser = $userRepo->findOneByUsername($username);
        if (!$foundUser) {
            $this->addFlash('login-error', 'no-user');
        } else {
            if (!password_verify($password, $foundUser->getPassword())) {
                $this->addFlash('login-error', 'wrong-password');
            } else {
                $this->get('session')->set('temp_user', $foundUser);
                return $this->redirectToRoute('login2_page');
            }
        }
        return $this->redirectToRoute('login_page');
    }

    public function login2Action(Request $request) {
        $session = $this->get('session');
        $user = $session->get('temp_user');
        $session->remove('temp_user');
        $code = $request->request->get('code');
        $ga = new GoogleAuthenticator();
        if (!$ga->verifyCode($user->getSecret(), $code, 2)) {
            return $this->redirectToRoute('login_page');
        }
        $this->authenticateUser($user);
        return $this->redirectToRoute('homepage');
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
            $true = true;
            $key = bin2hex(openssl_random_pseudo_bytes(16, $true));
            $user->setKey($key);
            $ga = new GoogleAuthenticator();
            $secret = $ga->createSecret();
            $user->setSecret($secret);
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($user);
            $em->flush();

            $this->authenticateUser($user);
            $this->get('session')->set('signup', $true);
            return $this->redirectToRoute('signup2_page');
        }
        return $this->redirectToRoute('signup_page');
    }

    public function addFileAction(Request $request) {
        $filename = $request->request->get('filename');
        $content = $request->request->get('content');
        $fileRepo = $this->getDoctrine()->getRepository(File::class);
        $foundFile = $fileRepo->findOneByName($filename);
        if (!$foundFile) {
            $em = $this->getDoctrine()->getManager();
            $file = new File();
            $file->setName($filename);
            $packedContent = $this->encryptContent($content);
            $file->setContent($packedContent);
            $em->persist($file);
            $users = $em->getRepository(User::class);
            foreach ($users as $user) {
                if ($user->getRole() === 'admin') {
                    $user->addFile($file);
                    $em->persist($user);
                }
            }
            $em->flush();
        }
        return $this->redirectToRoute('homepage');
    }

    public function checkUserPermissionAction(Request $request) {
        $username = $request->request->get('username');
        $filename = $request->request->get('filename');
        $checked = $request->request->get('checked');
        $em = $this->getDoctrine()->getManager();
        $fileRepo = $em->getRepository(File::class);
        $userRepo = $em->getRepository(User::class);
        $user = $userRepo->findOneByUsername($username);
        $file = $fileRepo->findOneByName($filename);
        if ($user && $file) {
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
                $user->setFiles($files);
            }
            $em->flush();
            $this->updateSession();
        }
        return $this->redirectToRoute('homepage');
    }

    public function updateLocalStorageAction() {
        $user = $this->updateSession();
        $key = $user->getKey();
        $localStorage = [];
        foreach ($user->getFiles() as $file) {
            $filename = $file->getName();
            $content = $this->decryptContent($file->getContent());
            $localStorage[] = ['name' => $filename, 'content' => $content];
        }
        return $this->json(['key' => $key, 'localstorage' => $localStorage]);
    }

    private function authenticateUser($user) {
        $session = $this->get('session');
        $session->set('user', $user);
    }

    private function updateSession() {
        $session = $this->get('session');
        $em = $this->getDoctrine()->getManager();
        $sessionUser = $session->get('user');
        if ($sessionUser) {
            $user = $em->getRepository(User::class)->find($sessionUser->getId());
            $session->set('user', $user);
        }
        $user = $session->get('user');
        return $user;
    }

    private function encryptContent($content) {
        $true = true;
        $iv = openssl_random_pseudo_bytes(16, $true);
        $pass = $this->getParameter('secret');
        $algo = $this->getParameter('encryption_method');
        $encryptedContent = openssl_encrypt($content, $algo, $pass, 1, $iv);
        $packedContent = bin2hex($iv) . '.' . bin2hex($encryptedContent);
        return $packedContent;
    }

    private function decryptContent($content) {
        $iv = hex2bin(strtok($content, '.'));
        $contentBody = hex2bin(strtok('.'));
        $pass = $this->getParameter('secret');
        $algo = $this->getParameter('encryption_method');
        $decryptedContent = openssl_decrypt($contentBody, $algo, $pass, 1, $iv);
        return $decryptedContent;
    }
}
