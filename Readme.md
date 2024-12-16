# library
# Library Management API

This is a RESTful API for managing a library system, including users, authors, and books. The API uses the Slim framework and JWT (JSON Web Token) for authentication and session management.

## Features

- User registration and authentication.
- CRUD operations for authors.
- CRUD operations for books.
- Token-based authentication for secure access.

---

## Endpoints

### 1. User Management

#### **1.1 Register User**

- **Endpoint:** `/user/register`
- **Method:** POST
- **Description:** Registers a new user.
- **Payload:**

  ```json
  {
      "username": "user1",
      "password": "password123"
  }
  ```

- **Response:**

  ```json
  {
      "status": "success",
      "data": null
  }
  ```

#### **1.2 Authenticate User**

- **Endpoint:** `/user/auth`
- **Method:** POST
- **Description:** Authenticates a user and generates a JWT token.
- **Payload:**

  ```json
  {
      "username": "user1",
      "password": "password123"
  }
  ```

- **Response:**

  ```json
  {
      "status": "success",
      "token": "<jwt_token>",
      "data": null
  }
  ```

---

### 2. Author Management

#### **2.1 Add Author**

- **Endpoint:** `/addauthors`
- **Method:** POST
- **Description:** Adds a new author. Requires a valid token.
- **Headers:**

  ```
  Authorization: Bearer <jwt_token>
  ```

- **Payload:**

  ```json
  {
      "name": "Author Name"
  }
  ```

- **Response:**

  ```json
  {
      "status": "success",
      "token": "<new_jwt_token>",
      "data": null
  }
  ```

#### **2.2 Update Author**

- **Endpoint:** `/updateauthors/{id}`
- **Method:** PUT
- **Description:** Updates an existing author by ID. Requires a valid token.
- **Payload:**

  ```json
  {
      "name": "Updated Author Name"
  }
  ```

#### **2.3 Get Authors**

- **Endpoint:** `/getauthors`
- **Method:** GET
- **Description:** Retrieves all authors. Requires a valid token.

- **Response:**

  ```json
  {
      "status": "success",
      "token": "<new_jwt_token>",
      "data": [
          { "id": 1, "name": "Author Name" }
      ]
  }
  ```

#### **2.4 Delete Author**

- **Endpoint:** `/deleteauthors/{id}`
- **Method:** DELETE
- **Description:** Deletes an author by ID. Requires a valid token.

---

### 3. Book Management

#### **3.1 Add Book**

- **Endpoint:** `/addbooks`
- **Method:** POST
- **Description:** Adds a new book. Requires a valid token.
- **Payload:**

  ```json
  {
      "title": "Book Title",
      "author_id": 1
  }
  ```

#### **3.2 Update Book**

- **Endpoint:** `/updatebooks/{id}`
- **Method:** PUT
- **Description:** Updates an existing book by ID. Requires a valid token.
- **Payload:**

  ```json
  {
      "title": "Updated Book Title",
      "author_id": 2
  }
  ```

#### **3.3 Get Books**

- **Endpoint:** `/getbooks`
- **Method:** GET
- **Description:** Retrieves all books. Requires a valid token.

#### **3.4 Delete Book**

- **Endpoint:** `/deletebooks/{id}`
- **Method:** DELETE
- **Description:** Deletes a book by ID. Requires a valid token.

---

## Database Schema

### Users
- `id` (Primary Key)
- `username`
- `password`

### Authors
- `id` (Primary Key)
- `name`

### Books
- `id` (Primary Key)
- `title`
- `author_id` (Foreign Key)

### Tokens
- `id` (Primary Key)
- `token`
- `userid` (Foreign Key)
- `exp`
- `used`

---

## Setup Instructions

1. Clone the repository.
2. Install dependencies:

   ```bash
   composer install
   ```

3. Configure the database connection in the `getConnection()` function.
4. Run the application:

   ```bash
   php -S localhost:8080 -t public
   ```

5. Use Postman or similar tools to test the API endpoints.

---

## Authentication

- All secured endpoints require a JWT token in the `Authorization` header:

  ```
  Authorization: Bearer <jwt_token>
  ```

- Tokens expire in 1 hour. A new token is provided in each response to continue the session.


