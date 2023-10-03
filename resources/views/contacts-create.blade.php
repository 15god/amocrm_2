<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить контакт</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script
        src="https://code.jquery.com/jquery-3.7.1.js"
        integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4="
        crossorigin="anonymous"></script>
</head>
<body>
<div class="flex flex-col justify-center items-center mt-10">

    <form id="contact-form" class="w-full max-w-lg">
        <div class="flex flex-wrap -mx-3 mb-6">
            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="grid-first-name">
                    Имя
                </label>
                <input value="{{ old('first_name') }}" name="first_name" class="appearance-none block w-full bg-gray-200 text-gray-700 border rounded py-3 px-4 mb-3 leading-tight focus:outline-none focus:bg-white" id="grid-first-name" type="text" placeholder="First name">
            </div>
            <div class="w-full md:w-1/2 px-3">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="grid-last-name">
                    Фамилия
                </label>
                <input value="{{ old('last_name') }}" name="last_name" class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" id="grid-last-name" type="text" placeholder="Last name">
            </div>
        </div>
        <div class="flex flex-wrap -mx-3 mb-6">
            <div class="w-full px-3">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="grid-email">
                    Email
                </label>
                <input value="{{ old('email') }}" name="email" class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 mb-3 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" id="grid-email" type="text" placeholder="Email....">
            </div>
        </div>
        <div class="flex flex-wrap -mx-3 mb-6">
            <div class="w-full px-3">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="grid-phone">
                    Телефон
                </label>
                <input value="{{ old('phone') }}" name="phone" class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 mb-3 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" id="grid-phone" type="number" placeholder="Phone number....">
            </div>
        </div>
        <div class="flex flex-wrap -mx-3 mb-2">
            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="grid-age">
                    Возраст
                </label>
                <input value="{{ old('age') }}" name="age" class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" id="grid-age" type="number" placeholder="Age...">
            </div>
            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="grid-gender">
                    Пол
                </label>
                <div class="relative">
                    <select name="gender" class="block appearance-none w-full bg-gray-200 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500" id="grid-gender">
                        <option value="m" {{old('gender') == 'Мужской' ? 'selected' : ''}}>Мужской</option>
                        <option value="f" {{old('gender') == 'Женский' ? 'selected' : ''}}>Женский</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                    </div>
                </div>
            </div>
        </div>
        <div class="my-5 w-full">
            <button type="button" id="submit-button" class="text-white bg-gray-700 hover:bg-gray-800 focus:ring-4 focus:outline-none focus:ring-gray-300 font-medium rounded-lg text-md w-full px-5 py-2.5 text-center dark:bg-gray-600 dark:hover:bg-gray-700 dark:focus:ring-gray-800">Отправить</button>
        </div>
    </form>
    <div id="error" class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:text-red-700" role="alert">
    </div>
    <script>

        $(document).on('click', '#submit-button', function () {

            let data = $('#contact-form').serializeArray().reduce(function(obj, item) {
                obj[item.name] = item.value;
                return obj;
            }, {});

            let json = JSON.stringify(data);

            $.ajax({
                type:'POST',
                url: "/contacts",
                data: json,
                contentType: "application/json",
                success: function(response){
                    $('#error').html('');
                    alert(response);
                },
                error: function(xhr){
                    $('#error').html('');
                    $.each(xhr.responseJSON.errors, function(key,value) {
                        $('#error').append('<div class="alert alert-danger">'+value+'</div');
                    });
                }
            });


            // fetch("/contacts", {
            //     method: "POST",
            //     body: json,
            //     headers: {
            //         "Content-type": "application/json; charset=UTF-8"
            //     }
            // })
        });
    </script>
</div>
</body>
</html>
