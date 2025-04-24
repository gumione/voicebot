<?php
namespace App\Controller;

use App\Repository\AudioRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class DebugInlineController extends AbstractController
{
    #[Route('/debug/inline', name: 'debug_inline', methods: ['GET','POST'])]
    public function __invoke(Request $req, AudioRepository $repo)
    {
        $query  = trim($req->request->get('q', ''));
        $page   = max(0, (int)$req->query->get('page', 0));
        $limit  = 50;
        $offset = $page * $limit;

        $results = $total = [];
        if ($query !== '') {
            $results = $repo->search($query, $limit, $offset);
            $total   = $repo->countSearch($query);
        }

        return $this->render('debug/inline.html.twig', [
            'query'    => $query,
            'results'  => $results,
            'page'     => $page,
            'hasNext'  => ($offset + $limit) < $total,
            'hasPrev'  => $page > 0,
        ]);
    }
}
