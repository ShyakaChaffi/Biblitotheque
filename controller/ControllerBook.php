<?php

require_once 'model/User.php';
require_once 'model/Book.php';
require_once 'model/Rental.php';
require_once 'framework/View.php';
require_once 'framework/Controller.php';
require_once 'framework/Tools.php';

class ControllerBook extends Controller {

    public function index() {
        $user = $this->get_user_or_redirect();
        $books = Book::get_all_books($user->id);
        $getUserRental = $user->get_rental_join_book_join_user_by_user_not_rented();
        $members = User::get_all_user();
        $usertoAddRent = $user;
        $msg = " ";
        $filter = [];


        if (isset($_GET["param1"])) {
            $filter = Tools::url_safe_decode($_GET['param1']);
            $msg = $filter["idUser"];
            if (!$filter)
                Tools::abort("bad url parameter");
        }
        if (isset($_POST["search"]) && isset($_POST["idUser"])) {
            $filter["search"] = $_POST["search"];
            $filter["idUser"] = $_POST["idUser"];
            $this->redirect("Book", "index", Tools::url_safe_encode($filter));
        }
        if ($filter) {
            $books = Book::get_book_by_critere($filter["search"], $filter["idUser"]);
        } else {
//             $books = Book::get_all_books($msg);
        }


        (new View("book_manager"))->show(array("books" => $books, "profile" => $user, "UserRentals" => $getUserRental, "msg" => $msg, "members" => $members, "actualpanier" => $usertoAddRent));
    }

    public function add_book() {
        $user = $this->get_user_or_redirect();
        if ($user->is_admin()) {
            $isbn = "";
            $title = "";
            $author = "";
            $editor = "";
            $errors = [];
            $picture_path = "";
            $nbcopies = "";
            if (isset($_POST["isbn"]) && isset($_POST["author"]) && isset($_POST["title"]) && isset($_POST["editor"]) && isset($_POST["nbCopie"])) {
                Book::set_book_attr_add($isbn, $title, $author, $editor, $nbcopies);
                $isbn = str_replace("-", "", $isbn);
                $errors = Book::rules_add_book(substr($isbn, 0, -1), $title, $author, $editor, $nbcopies);
                if (isset($_FILES['picture']) && isset($_FILES['picture']['name']) && $_FILES['picture']['name'] != '') {
                    if ($_FILES['picture']['error'] == 0)
                        $picture_path = Book::add_picture($picture_path, $title);
                } else
                    $picture_path = NULL;
                if (Book::existIsbn($isbn))
                    $errors[] = "ISBN existe deja !";
                if (empty($errors)) {
                    $book = new Book(0, Book::calcul_isbn(substr($isbn, 0, -1)), $title, $author, $editor, $picture_path, $nbcopies);
                    $book->create();
                    $this->redirect("book", "index");
                }
            }
        }
        (new View("add_book"))->show(array("errors" => $errors, "isbn" => $isbn, "title" => $title, "author" => $author, "editor" => $editor, "nbCopie" => $nbcopies));
    }

    public function delete_book() {
        $user = Controller::get_user_or_redirect();
        if ($user->is_admin()) {
            $usertoAddRent = $user;
            $getUserRental = $user->get_rental_join_book_join_user_by_user_not_rented();
            $books = Book::get_all_books($usertoAddRent->id);
            $members = User::get_all_user();

            if (isset($_POST["delbook"])) {
                $delbook = Book::get_book_by_id($_POST["delbook"]);
            }
            if (isset($_POST["conf"])) {
                $delbook = Book::get_book_by_id($_POST["conf"]);
                unlink("uploads/" . $delbook->picture);
                $delbook->delete_book();
                $this->redirect("book", "index");
            } (new View("delete_confirm"))->show(array("book" => $delbook));
        }
    }

    public function book_detail() {
        $user = $this->get_user_or_redirect();
        if (isset($_POST["idbook"])) {
            $value = $_POST["idbook"];
            $book = Book::get_book_by_id($value);
        } (new View("book_detail"))->show(array("book" => $book, "profile" => $user));
    }

    public function edit_book() {
        $user = $this->get_user_or_redirect();
        if ($user->is_admin()) {
            $book = "";
            $errors = [];
            $oldpath = "";
            $nbCopie = "";
            if (isset($_POST['editbook'])) {
                $book = Book::get_book_by_id($_POST['editbook']);
                $oldpath = $book->picture;
                $book->isbn = substr($book->isbn, 0, 12);
            }
            if (isset($_POST["cancel"]))  // boutton annuler
                $this->redirect("book", "index");
            if (isset($_POST['idbook']) && isset($_POST['isbn']) || isset($_POST['title']) || isset($_POST['editor']) || isset($_POST['author']) || isset($_POST['nbCopie'])) {
                $book = Book::get_book_by_id($_POST['idbook']);
                $book->isbn = substr($book->isbn, 0, 12);
                Book::set_book_attr_edit($book);
                if (isset($_FILES['picture']) && isset($_FILES['picture']['name']) && $_FILES['picture']['name'] != '') {
                    if ($_FILES['picture']['error'] == 0) {
                        $book->edit_picture();
                    }
                } else
                    $book->picture = NULL;
                $errors = Book::rules_add_book($book->isbn, $book->title, $book->author, $book->editor, $book->nbCopies);

                $isbn13 = Book::calcul_isbn($book->isbn);
                $thisBook = Book::get_book_by_id($_POST["idbook"]);
                if ($thisBook->isbn !== $isbn13)
                    if (Book::existIsbn($isbn13))
                        $errors[] = "ISBN existe deja !";

                if (empty($errors)) {
                    $book->isbn = Book::calcul_isbn($book->isbn);
                    $book->update();
                    $this->redirect("book", "index");
                }
            }
            (new View("edit_book"))->show(array("book" => $book, "errors" => $errors, "profile" => $user, "nbCopie" => $nbCopie));
        } else
            $this->redirect("book", "index");
    }

    public function delete_img() {
        $user = $this->get_user_or_redirect();
        if ($user->is_admin()) {
            $book = "";
            $errors = [];
            $nbCopie = "";
            if (isset($_POST["delimageH"])) {
                $edit = $_POST["delimageH"];
                $book = Book::get_book_by_id($edit);
                if ($book->picture !== NULL) {
                    $book->delete_image();
                    unlink("uploads/" . $book->picture);
                }
                $book = Book::get_book_by_id($edit);
                (new View("edit_book"))->show(array("book" => $book, "errors" => $errors, "profile" => $user, "nbCopie" => $nbCopie)); // pour "refresh" l'img suppr
            }
        } else
            $this->redirect("book", "index");
    }

    public function get_search() {
        if (isset($_GET['param1']) && !$_GET['param1'] == "" && isset($_GET['param2'])) {
            if ($_GET['param1'] !== " ") {
                $result = Book::get_book_by_critere($_GET['param1'], $_GET['param2']);
                echo json_encode($result);
            } else {
                $result = Book::get_all_books($_GET['param2']);
                echo json_encode($result);
            }
        }
    }

    public function getIsbn() {
        $isbn = "";
        if (isset($_GET['param1'])) {
            $isbn = Book::calcul_isbn_js($_GET['param1']);
            echo json_encode($isbn);
        }
    }

    public function addFeatures() {
        if (isset($_GET['param1'])) {
            $isbn = Book::isbn_format_EAN_13($_GET['param1']);
            echo json_encode($isbn);
        }
    }

    public function isbnExists() {
        $res = "true";
        if (isset($_POST["isbn"]) && $_POST["isbn"] !== "") {
            if (isset($_POST["idbook"]) && $_POST["idbook"] !== "") {
                $thisBook = Book::get_book_by_id($_POST["idbook"]);
                if ($thisBook->isbn === Book::calcul_isbn($_POST["isbn"]))
                    $res = "true";
                elseif (Book::existIsbn(Book::calcul_isbn($_POST["isbn"]))) {
                    $res = "false";
                }
            }


            echo $res;
        }
    }

}
