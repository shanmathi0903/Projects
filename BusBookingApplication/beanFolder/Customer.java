package beanFolder;

public class Customer extends User{
	public Customer(int id,String name,long number,String email,String password){
		this.setId(id);
		this.setName(name);
		this.setPhoneNumber(number);
		this.setEmail(email);
		this.setPassword(password);
	}
}