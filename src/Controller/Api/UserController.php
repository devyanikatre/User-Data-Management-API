<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use League\Csv\Reader;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route; // Added this import for route annotations
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface; // Added this import to use parameter bag
use Symfony\Component\Messenger\MessageBusInterface;
class UserController extends AbstractController
{
    private $params;
    private $bus;

    public function __construct(ParameterBagInterface $params, MessageBusInterface $bus)
    {
        $this->params = $params;
        $this->bus = $bus;
    }

    /**
     * @Route("/api/user/upload", name="api_user_upload", methods={"POST"})
     */
    public function upload(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $file = $request->files->get('file');
        if (!$file) {
            return new Response('No file uploaded', Response::HTTP_BAD_REQUEST);
        }

        try {
            $csv = Reader::createFromPath($file->getPathname(), 'r');
            $csv->setHeaderOffset(0);

            foreach ($csv as $record) {
                $user = new User();
                $user->setName($record['name']);
                $user->setEmail($record['email']);
                $user->setUsername($record['username']);
                $user->setAddress($record['address']);
                $user->setRole($record['role']);
                $em->persist($user);
            }

            $em->flush();

            foreach ($csv as $record) {
                try {
                    $email = (new Email())
                        ->from($this->params->get('MAILER_FROM'))
                        ->to($record['email'])
                        ->subject('User Data Stored')
                        ->text('Your data has been successfully stored.');

                    $mailer->send($email);
                } catch (\Exception $e) {
                    // Log the email sending error and continue
                    $this->get('logger')->error('Failed to send email to ' . $record['email'] . ': ' . $e->getMessage());
                }
                
            }

            return new Response('Data uploaded successfully', Response::HTTP_OK);
        } catch (\Exception $e) {
            return new Response('Error uploading data: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @Route("/api/user/view", name="api_user_view", methods={"GET"})
     */
    public function viewUsers(EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(User::class)->findAll();
        $data = [];

        foreach ($users as $user) {
            $data[] = [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'address' => $user->getAddress(),
                'role' => $user->getRole()
            ];
        }

        return $this->json($data);
    }

    /**
     * @Route("/api/backup", name="api_backup", methods={"POST"})
     */
    public function backup(): Response
    {
        $backupFile = $this->params->get('backup_file_path');
        $dbUser = $this->params->get('DB_USER');
        $dbPassword = $this->params->get('DB_PASSWORD');
        $dbName = $this->params->get('DB_NAME');
        
        if (!$dbUser || !$dbPassword || !$dbName) {
            return new Response('Database credentials not set', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        exec("mysqldump --user={$dbUser} --password={$dbPassword} {$dbName} > {$backupFile}");

        return new Response('Database backup created successfully', Response::HTTP_OK);
    }

    /**
     * @Route("/api/restore", name="api_restore", methods={"POST"})
     */
    public function restore(): Response
    {
        $backupFile = $this->params->get('backup_file_path');
        $dbUser = $this->params->get('DB_USER');
        $dbPassword = $this->params->get('DB_PASSWORD');
        $dbName = $this->params->get('DB_NAME');

        if (!$dbUser || !$dbPassword || !$dbName) {
            return new Response('Database credentials not set', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        exec("mysql --user={$dbUser} --password={$dbPassword} {$dbName} < {$backupFile}");

        return new Response('Database restored successfully', Response::HTTP_OK);
    }
}
