<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Blog;
use AppBundle\Entity\Comments;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Config\Definition\Exception\Exception;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction()
    {
        $blogs = $this->getDoctrine()->getRepository(Blog::class)->findAll(array(),array('id'=>'DESC'));
        return $this->render('default/index.html.twig',array('blogs'=>$blogs));
    }



    /**
     * @Route("/posts/{post_url}", name="single_post")
     */
    public function postsAction(Request $request, $post_url)
    {
        $comment=new Comments();
        $blog = $this->getDoctrine()->getRepository(Blog::class)->findBy(array('url'=>$post_url));
        $blog_id = $blog[0]->getId();

        $form = $this->createFormBuilder($comment)
        ->add('text', TextareaType::class, array('label'=>'Enter your comment', 'attr' => array('rows'=>4, 'placeholder'=>'Enter your comment', 'class' => 'form-control')))
        ->add('save', SubmitType::class, array('label' => 'Comment','attr'=>array('class'=>'submit_button')))
        ->getForm();
        $form->handleRequest($request);

        // adding comment
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $comment = $form->getData();
            $comment->setBlogId($blog_id);
            $comment->setCreatedDate(new \DateTime());
            $em->persist($comment);
            $em->flush();

            $session = new Session();
            if (session_status() == PHP_SESSION_NONE) {
                $session->start();
            }
            $session->getFlashBag()->add('success', 'The comment is added');
            return $this->redirectToRoute('single_post',array('post_url'=>$post_url));
        }
        
        $comments = $this->getDoctrine()->getRepository(Comments::class)->findBy(array('blogId'=>$blog_id),array('createdDate'=>'DESC','id'=>'DESC')); 
        return $this->render('default/single_post.html.twig',array('form' => $form->createView(),'blog'=>$blog[0],'comments'=>$comments));
    }

    /**
     * @Route("/post/add", name="add_post")
     */
    public function add_postAction(Request $request)
    {
        
        $session = new Session();
        if (session_status() == PHP_SESSION_NONE) {
            $session->start();
        }
        try{
            $blog = new Blog();
            
            $form = $this->createFormBuilder($blog)
                ->add('title', TextType::class, array('label'=>'Post title', 'attr' => array('placeholder'=>'Enter title of post', 'maxlength' => 255)))
                ->add('url', TextType::class, array('label'=>'Post URL', 'attr' => array('placeholder'=>'Enter URL of post','maxlength' => 255)))
                ->add('text', TextareaType ::class, array('label'=>'Post text', 'attr' => array('rows'=>8, 'placeholder'=>'Enter text of post')))
                ->add('src', FileType::class, array('required'=>false, 'label' => 'Blog (Image file)'))                        
                ->add('save', SubmitType::class, array('label' => 'Create Blog','attr'=>array('class'=>'submit_button')))
                ->getForm();
    
    
            $form->handleRequest($request);
             
            if ($form->isSubmitted()) {
                $em = $this->getDoctrine()->getManager();
                $blog = $form->getData();
                $src = $blog->getSrc();
                $srcName='';
                if($src && $this->isImage($src->guessExtension())){
                    $srcName = md5(uniqid()).'.'.$src->guessExtension();
                    $src->move(
                        $this->container->getParameter('file_directory'),
                        $srcName
                    );
                }
                $blog->setSrc($srcName);    
    
                $session = new Session();
                if (session_status() == PHP_SESSION_NONE) {
                    $session->start();
                }
                try{
                    $em->persist($blog);
                    $em->flush();
                    $session->getFlashBag()->add('success', 'The blog is created');
                }catch(\Exception $e){
                    $session->getFlashBag()->add('error', 'Error');
                }
                
                return $this->redirectToRoute('add_post');
            }                
        }catch(\Exception $ex){
            $error_message="User input error";
            /*
                switch($ex->getErrorCode()){
                    case 1062:
                        $error_message="Duplicated URL";
                        break;
                    default:
                }   
            $session->getFlashBag()->add('error', $ex->getErrorMessage());
             */   
            $session->getFlashBag()->add('error', $error_message);
            return $this->redirectToRoute('edit_post',array('post_url'=>$post_url));
        }
        

        return $this->render('default/add_post.html.twig', array(
            'form' => $form->createView(),
        ));        
    }
    public function isImage($extension){
        $allowed=array('jpg','png','jpeg');
        if(in_array($extension,$allowed)){
            return true;
        }
        return false;
    }
    /**
     * @Route("edit/{post_url}", name="edit_post")
     */
    public function editAction(Request $request,$post_url)
    {
        
        $session = new Session();
        if (session_status() == PHP_SESSION_NONE) {
            $session->start();
        }
        try{
            $blog = new Blog();
            $blog=$this->getBlog($post_url);
            $form = $this->createFormBuilder()
                ->add('title', TextType::class, array('label'=>'Post title', 'attr' => array('value'=>$blog->getTitle(),'maxlength' => 255)))
                ->add('url', TextType::class, array('label'=>'Post URL', 'attr' => array('value'=>$blog->getUrl(),'maxlength' => 255)))
                ->add('text', TextareaType ::class, array('label'=>'Post text', 'data'=>$blog->getText(), 'attr' => array('rows'=>8)))
                ->add('src', FileType::class, array('required'=>false, 'label' => 'Blog (Image file)'))                        
                ->add('save', SubmitType::class, array('label' => 'Edit Blog','attr'=>array('class'=>'submit_button')))
                ->getForm();


            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                //$blog = $form->getData(); 
                $srcName=$blog->getSrc();
                if($form->getData()['src']){
                    $src = $form->getData()['src'];
                    if($this->isImage($src->guessExtension())){
                        $srcName = md5(uniqid()).'.'.$src->guessExtension();
                        $src->move(
                            $this->container->getParameter('file_directory'),
                            $srcName
                        );
                    }
                } 
                $blog->setSrc($srcName); 
                $blog->setTitle($form->getData()['title']); 
                $blog->setUrl($form->getData()['url']); 
                $blog->setText($form->getData()['text']); 
                $em->flush();
                $session->getFlashBag()->add('success', 'The blog is updated');
                return $this->redirectToRoute('edit_post',array('post_url'=>$form->getData()['url']));
            }  
        }catch(\Exception $ex){
            $error_message="User input error";
            /*
                switch($ex->getErrorCode()){
                    case 1062:
                        $error_message="Duplicated URL";
                        break;
                    default:
                }   
            $session->getFlashBag()->add('error', $ex->getErrorMessage());
             */   
            $session->getFlashBag()->add('error', $error_message);
            return $this->redirectToRoute('edit_post',array('post_url'=>$post_url));
        }

        return $this->render('default/edit_post.html.twig', array(
            'current_image'=>$blog->getSrc(),
            'form' => $form->createView(),
        ));        
    }

    public function getBlog($post_url){
        $data=$blog = $this->getDoctrine()->getRepository(Blog::class)->findBy(array('url'=>$post_url));
        if(isset($data[0])){
            return $data[0];
        }
        $this->errorPage();
    }
 
    /**
     * @Route("error", name="error")
     */
    public function errorPage(){
        return $this->render('default/error.html.twig');
        die;
    }
}
