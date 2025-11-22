<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal List</title>
</head>
<body>
    <div>
        <h1>Animal List</h1>
        <a href="{{ url('animals/create') }}">Add New Animal</a>

        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Breed</th>
                    <th>Health Status</th>
                    <th>Birth Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr>
                    <td>{{ $product->id }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->type }}</td>
                    <td>{{ $product->breed }}</td>
                    <td>{{ $product->health_status }}</td>
                    <td>{{ $product->birth_date }}</td>
                    <td>
                        <a href="{{ url('animals/'.$product->id.'/edit') }}">Edit</a>

                        <form action="{{ url('api/products/'.$product->id) }}" method="POST" style="display:inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirm('Are you sure you want to delete this animal?')">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
