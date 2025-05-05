<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleForm;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Comment;
use App\Form\CommentForm;
use App\Entity\Like;

#[Route('/article')]
final class ArticleController extends AbstractController
{
    #[Route(name: 'app_article_index', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
        return $this->render('article/index.html.twig', [
            'articles' => $articleRepository->findAll(),
        ]);
    }

   #[Route('/new', name: 'app_article_new', methods: ['GET', 'POST'])]
	public function new(Request $request, EntityManagerInterface $entityManager): Response
{
    $article = new Article();
    $form = $this->createForm(ArticleForm::class, $article);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $article->setCreatedAt(new \DateTimeImmutable());
        $article->setAuthor($this->getUser()); // On lie l’auteur à l’utilisateur connecté

        $entityManager->persist($article);
        $entityManager->flush();

        return $this->redirectToRoute('app_article_index', [], Response::HTTP_SEE_OTHER);
    }

    return $this->render('article/new.html.twig', [
        'article' => $article,
        'form' => $form,
    ]);
}


#[Route('/{id}', name: 'app_article_show', methods: ['GET', 'POST'])]
public function show(
    Request $request,
    Article $article,
    EntityManagerInterface $entityManager
): Response {
    $comment = new Comment();
    $form = $this->createForm(CommentForm::class, $comment);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $comment->setCreatedAt(new \DateTimeImmutable());
        $comment->setAuthor($this->getUser());
        $comment->setArticle($article);

        $entityManager->persist($comment);
        $entityManager->flush();

        return $this->redirectToRoute('app_article_show', ['id' => $article->getId()]);
    }

    return $this->render('article/show.html.twig', [
        'article' => $article,
        'form' => $form->createView(),
        'comments' => $article->getComments(), // récupère tous les commentaires liés à l'article
    ]);
}

    #[Route('/{id}/edit', name: 'app_article_edit', methods: ['GET', 'POST'])]
	public function edit(Request $request, Article $article, EntityManagerInterface $entityManager): Response
	{
		// Vérifie que l'utilisateur est bien l'auteur de l'article
		if ($this->getUser() !== $article->getAuthor() && !$this->isGranted('ROLE_ADMIN')) {
			$this->addFlash('error', "Vous n'êtes pas autorisé à modifier cet article.");
			return $this->redirectToRoute('app_article_show', ['id' => $article->getId()]);
		}

		$form = $this->createForm(ArticleForm::class, $article);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$entityManager->flush();

			return $this->redirectToRoute('app_article_index', [], Response::HTTP_SEE_OTHER);
		}

		return $this->render('article/edit.html.twig', [
			'article' => $article,
			'form' => $form,
		]);
	}


    #[Route('/{id}', name: 'app_article_delete', methods: ['POST'])]
	public function delete(Request $request, Article $article, EntityManagerInterface $entityManager): Response
	{
		if ($this->getUser() !== $article->getAuthor() && !$this->isGranted('ROLE_ADMIN')) {
			$this->addFlash('error', "Vous n'êtes pas autorisé à modifier cet article.");
			return $this->redirectToRoute('app_article_show', ['id' => $article->getId()]);
		}

		if ($this->isCsrfTokenValid('delete' . $article->getId(), $request->getPayload()->getString('_token'))) {
			$entityManager->remove($article);
			$entityManager->flush();
		}

		return $this->redirectToRoute('app_article_index', [], Response::HTTP_SEE_OTHER);
	}


		#[Route('/{id}/like', name: 'app_article_like', methods: ['POST'])]
	public function like(Article $article, EntityManagerInterface $entityManager): Response
	{
		$user = $this->getUser();

		// sécurité : seul un utilisateur connecté peut liker
		if (!$user) {
			$this->addFlash('error', 'Vous devez être connecté pour liker un article.');
			return $this->redirectToRoute('app_login');
		}

		// Vérifie si l'utilisateur a déjà liké l'article
		$existingLike = $article->getLikes()->filter(fn(Like $like) => $like->getUser() === $user)->first();

		if ($existingLike) {
			$entityManager->remove($existingLike);
			$this->addFlash('success', 'Like retiré.');
		} else {
			$like = new Like();
			$like->setUser($user);
			$like->setArticle($article);
			$like->setCreatedAt(new \DateTimeImmutable());

			$entityManager->persist($like);
			$this->addFlash('success', 'Article liké !');
		}

		$entityManager->flush();

		return $this->redirectToRoute('app_article_show', ['id' => $article->getId()]);
	}
}
